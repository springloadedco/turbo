---
name: laravel-actions
description: Laravel Action class patterns for encapsulating business logic. Use when creating actions, refactoring complex logic, building service objects, or when user asks to "create an action", "extract business logic", "add a sync action".
allowed-tools: Read, Glob, Grep, Edit, Write, Bash
---

# Laravel Action Classes

## Core Pattern

Action classes encapsulate complex business logic into single-purpose, reusable units.

```php
<?php

namespace App\Actions;

use App\Contracts\Action;

class SyncResourceRelationships extends Action
{
    public function handle(Resource $resource, Collection $relationIds): void
    {
        // Business logic here
    }
}
```

## Base Action Class

```php
<?php

namespace App\Contracts;

abstract class Action implements ActionInterface
{
    public static function make(): static
    {
        return resolve(static::class);
    }

    public static function run(...$arguments)
    {
        return static::make()->handle(...$arguments);
    }
}
```

## Usage

### Static Run Method

```php
// Most common usage - creates instance and calls handle()
SyncPostTags::run($post, $tagIds, $user);
```

### Factory Method

```php
// When you need to configure the action first
$action = SyncPostTags::make();
$action->handle($post, $tagIds, $user);
```

### In Controllers

```php
class UpdatePostController extends Controller
{
    public function __invoke(UpdatePostRequest $request, Post $post)
    {
        SyncPostTags::run(
            $post,
            collect($request->validated('tag_ids')),
            $request->user()
        );

        return redirect()->back();
    }
}
```

## Naming Convention

**Pattern:** `{Action}{Resource}{Detail}` or `{Action}{Resources}`

| Name | Purpose |
|------|---------|
| `SyncPostTags` | Sync many-to-many relationship |
| `EnrichPostMetadata` | Fetch and update metadata |
| `ImportPostsFromFeed` | Import from external source |
| `CopyPostToNewDraft` | Complex copy operation |
| `CalculateUserStats` | Business calculation |
| `SendPostNotifications` | Notification dispatch |

## File Location

```
app/Actions/{ActionName}.php
```

Nested for complex features:
```
app/Actions/
├── SyncPostTags.php
└── Copying/
    ├── CopyPostMetadata.php
    └── Content/
        ├── CopyPostSections.php
        └── CopyPostMedia.php
```

## Constructor Dependency Injection

Inject services via constructor:

```php
class EnrichPostMetadata extends Action
{
    public function __construct(
        public MetadataService $metadataService,
    ) {
    }

    public function handle(Post $post): void
    {
        $data = $this->metadataService->fetchMetadata($post->external_url);
        // Use injected service
    }
}
```

Dependencies are auto-resolved when using `::make()` or `::run()`.

## Action Structure

### Simple Action

```php
class UpdatePostStatus extends Action
{
    public function handle(Post $post, string $status): void
    {
        $post->update(['status' => $status]);
    }
}
```

### Action with Side Effects

```php
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

    public function handle(Post $post, Collection $tagIds, ?User $initiator = null): void
    {
        $this->initiator = $initiator;
        $this->syncTags($post, $tagIds);

        if ($this->shouldSendNotification()) {
            $this->sendNotification($post);
        }
    }

    private function syncTags(Post $post, Collection $tagIds): void
    {
        $this->findRemovedTags($post, $tagIds);
        $this->findAddedTags($post, $tagIds);

        $post->tags()->sync($tagIds);
    }

    private function findRemovedTags(Post $post, Collection $tagIds): void
    {
        $this->removedTags = $post->tags()
            ->whereNotIn('id', $tagIds)
            ->get();
    }

    private function findAddedTags(Post $post, Collection $tagIds): void
    {
        $existingIds = $post->tags->pluck('id');
        $this->addedTags = Tag::whereIn('id', $tagIds->diff($existingIds))->get();
    }

    private function shouldSendNotification(): bool
    {
        return $this->addedTags->isNotEmpty()
            || $this->removedTags->isNotEmpty();
    }

    protected function sendNotification(Post $post): void
    {
        $post->author->notify(
            new TagsUpdatedNotification(
                $post,
                $this->addedTags,
                $this->removedTags,
                $this->initiator
            )
        );
    }
}
```

## Pipeline Actions

For use in Laravel Pipeline:

```php
class EnrichPostMetadata extends Action
{
    public function __construct(
        public MetadataService $metadataService,
    ) {
    }

    public function handle(Post $post, \Closure $next)
    {
        // Do work
        $this->enrichMetadata($post);

        // Pass to next action in pipeline
        return $next($post);
    }

    private function enrichMetadata(Post $post): void
    {
        // Implementation
    }
}
```

Usage in pipeline:

```php
Pipeline::send($post)
    ->through([
        EnrichPostMetadata::class,
        SyncPostCategories::class,
        UpdatePostStats::class,
    ])
    ->thenReturn();
```

## When to Use Actions

### Use Actions For

- Complex business logic that spans multiple models
- Operations that trigger side effects (notifications, events)
- Logic reused across multiple controllers/commands
- Operations that need to be tested in isolation
- Sync/import/export operations

### Use Services For

- Stateless utility functions
- External API integrations
- Cross-cutting concerns (toast messages, drawers)

### Keep in Controller When

- Simple CRUD with no side effects
- Direct model operations
- Single-use logic

## Testing Actions

```php
class SyncPostTagsTest extends TestCase
{
    #[Test]
    public function it_syncs_tags_to_post(): void
    {
        $post = Post::factory()->create();
        $tags = Tag::factory()->count(3)->create();

        SyncPostTags::run(
            $post,
            $tags->pluck('id')
        );

        $this->assertCount(3, $post->fresh()->tags);
        $tags->each(fn ($tag) =>
            $this->assertDatabaseHas('post_tag', [
                'post_id' => $post->id,
                'tag_id' => $tag->id,
            ])
        );
    }

    #[Test]
    public function it_sends_notification_when_tags_change(): void
    {
        Notification::fake();

        $post = Post::factory()->create();
        $user = User::factory()->create();

        SyncPostTags::run(
            $post,
            collect([1, 2, 3]),
            $user
        );

        Notification::assertSentTo($post->author, TagsUpdatedNotification::class);
    }
}
```

## See Also

- `examples/` - Full action examples
