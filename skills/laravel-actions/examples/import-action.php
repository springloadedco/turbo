<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Post;
use App\Models\Tag;
use App\Services\FeedImportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports posts from an external RSS/Atom feed.
 *
 * This action:
 * 1. Fetches data from external feed
 * 2. Validates the entries
 * 3. Creates or updates posts
 * 4. Associates categories and tags
 *
 * Wraps everything in a transaction for data integrity.
 *
 * Usage:
 *   $result = ImportPostsFromFeed::run($feedUrl, $defaultCategoryId);
 */
class ImportPostsFromFeed extends Action
{
    private array $importedEntries;
    private Collection $errors;
    private int $created = 0;
    private int $updated = 0;

    public function __construct(
        private FeedImportService $feedService,
    ) {
        $this->errors = collect();
    }

    /**
     * Main entry point - returns import statistics.
     */
    public function handle(string $feedUrl, ?int $defaultCategoryId = null): array
    {
        $this->importedEntries = $this->fetchFeed($feedUrl);

        if (empty($this->importedEntries)) {
            $this->errors->push('No entries found in feed');
            return $this->getResult();
        }

        DB::transaction(function () use ($defaultCategoryId) {
            foreach ($this->importedEntries as $entry) {
                $this->processEntry($entry, $defaultCategoryId);
            }
        });

        return $this->getResult();
    }

    /**
     * Fetch and parse the external feed.
     */
    private function fetchFeed(string $feedUrl): array
    {
        return $this->feedService->parseFeed($feedUrl);
    }

    /**
     * Process a single feed entry.
     */
    private function processEntry(array $entry, ?int $defaultCategoryId): void
    {
        if (!$this->validateEntry($entry)) {
            return;
        }

        $post = $this->findOrCreatePost($entry);
        $isNew = !$post->exists;

        $this->updatePostAttributes($post, $entry, $defaultCategoryId);
        $this->syncTags($post, $entry);

        if ($isNew) {
            $this->created++;
        } else {
            $this->updated++;
        }
    }

    /**
     * Validate entry has required fields.
     */
    private function validateEntry(array $entry): bool
    {
        $required = ['title', 'guid', 'content'];

        foreach ($required as $field) {
            if (empty($entry[$field])) {
                $this->errors->push("Entry missing required field: {$field}");
                return false;
            }
        }

        return true;
    }

    /**
     * Find existing post by GUID or create new one.
     */
    private function findOrCreatePost(array $entry): Post
    {
        return Post::firstOrNew([
            'external_guid' => $entry['guid'],
        ]);
    }

    /**
     * Update post with imported data.
     */
    private function updatePostAttributes(Post $post, array $entry, ?int $defaultCategoryId): void
    {
        $post->fill([
            'title' => $entry['title'],
            'body' => $entry['content'],
            'excerpt' => $entry['summary'] ?? null,
            'external_url' => $entry['link'] ?? null,
            'published_at' => $entry['published_at'] ?? now(),
            'category_id' => $defaultCategoryId,
        ]);

        $post->save();
    }

    /**
     * Sync tags from imported data.
     */
    private function syncTags(Post $post, array $entry): void
    {
        if (empty($entry['tags'])) {
            return;
        }

        $tagNames = collect($entry['tags']);

        $tagIds = $tagNames->map(function ($name) {
            return Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            )->id;
        });

        $post->tags()->sync($tagIds);
    }

    /**
     * Get import result summary.
     */
    private function getResult(): array
    {
        return [
            'success' => $this->errors->isEmpty(),
            'created' => $this->created,
            'updated' => $this->updated,
            'errors' => $this->errors->toArray(),
        ];
    }

    /**
     * Get any validation errors.
     */
    public function getErrors(): Collection
    {
        return $this->errors;
    }
}
