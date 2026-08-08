<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\PostTagsUpdatedNotification;
use App\Repositories\UserRepository;
use Illuminate\Support\Collection;

/**
 * Syncs tags for a post.
 *
 * This action:
 * 1. Removes tags no longer in the list
 * 2. Attaches newly added tags
 * 3. Sends notifications if any changes were made
 *
 * Usage:
 *   SyncPostTags::run($post, $tagIds, $user);
 */
class SyncPostTags extends Action
{
    private Collection $removedTags;
    private Collection $addedTags;
    private ?User $initiator;

    public function __construct()
    {
        $this->removedTags = collect();
        $this->addedTags = collect();
        $this->initiator = null;
    }

    /**
     * Main entry point for the action.
     */
    public function handle(Post $post, Collection $tagIds, ?User $initiator = null): void
    {
        $this->initiator = $initiator;
        $this->syncTags($post, $tagIds);

        if ($this->shouldSendNotification()) {
            $this->sendNotification($post);
        }
    }

    /**
     * Coordinate the sync operation.
     */
    private function syncTags(Post $post, Collection $tagIds): void
    {
        $this->trackRemovedTags($post, $tagIds);
        $this->trackAddedTags($post, $tagIds);

        // Perform the actual sync
        $post->tags()->sync($tagIds);
    }

    /**
     * Track which tags are being removed.
     */
    private function trackRemovedTags(Post $post, Collection $tagIds): void
    {
        $this->removedTags = $post->tags()
            ->whereNotIn('tags.id', $tagIds)
            ->get();
    }

    /**
     * Track which tags are being added.
     */
    private function trackAddedTags(Post $post, Collection $tagIds): void
    {
        $existingTagIds = $post->tags->pluck('id');

        $this->addedTags = Tag::whereIn('id', $tagIds->diff($existingTagIds))->get();
    }

    /**
     * Determine if notification should be sent.
     */
    private function shouldSendNotification(): bool
    {
        return $this->addedTags->isNotEmpty() || $this->removedTags->isNotEmpty();
    }

    /**
     * Send notification to relevant users.
     */
    protected function sendNotification(Post $post): void
    {
        app(UserRepository::class)
            ->getSubscribersForPost($post)
            ->each(function (User $user) use ($post) {
                $user->notify(new PostTagsUpdatedNotification(
                    $post,
                    $this->addedTags,
                    $this->removedTags,
                    $this->initiator
                ));
            });
    }
}
