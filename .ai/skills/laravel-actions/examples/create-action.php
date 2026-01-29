<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\ContentEnrichmentService;

/**
 * Enriches a post with suggested categories and tags based on content analysis.
 *
 * This action fetches suggestions from an external service and creates
 * associations for categories and tags that match existing records.
 *
 * Designed for use in Laravel Pipeline for post creation flows.
 *
 * Usage (standalone):
 *   EnrichPostMetadata::run($post);
 *
 * Usage (in pipeline):
 *   Pipeline::send($post)->through([EnrichPostMetadata::class, ...])->thenReturn();
 */
class EnrichPostMetadata extends Action
{
    /**
     * Inject dependencies via constructor.
     * Resolved automatically when using ::make() or ::run().
     */
    public function __construct(
        public ContentEnrichmentService $enrichmentService,
    ) {
    }

    /**
     * Handle the action.
     *
     * Accepts $next closure for pipeline compatibility.
     */
    public function handle(Post $post, \Closure $next = null)
    {
        if ($post->body) {
            $suggestions = $this->getSuggestions($post);
            $this->applyCategorySuggestion($post, $suggestions);
            $this->applyTagSuggestions($post, $suggestions);
        }

        // Support pipeline pattern - pass to next action if provided
        return $next ? $next($post) : $post;
    }

    /**
     * Get content suggestions from external service.
     */
    private function getSuggestions(Post $post): array
    {
        return $this->enrichmentService->analyzeContent($post->body);
    }

    /**
     * Apply category suggestion if we have a matching category.
     */
    private function applyCategorySuggestion(Post $post, array $suggestions): void
    {
        if (empty($suggestions['category']) || $post->category_id) {
            return;
        }

        $category = Category::where('slug', $suggestions['category'])->first();

        if ($category) {
            $post->update(['category_id' => $category->id]);
        }
    }

    /**
     * Apply tag suggestions, creating associations for existing tags.
     */
    private function applyTagSuggestions(Post $post, array $suggestions): void
    {
        if (empty($suggestions['tags'])) {
            return;
        }

        $suggestedSlugs = collect($suggestions['tags']);

        $matchingTags = Tag::whereIn('slug', $suggestedSlugs)->get();

        // Only add tags that aren't already attached
        $newTagIds = $matchingTags
            ->pluck('id')
            ->diff($post->tags->pluck('id'));

        if ($newTagIds->isNotEmpty()) {
            $post->tags()->attach($newTagIds);
        }
    }
}
