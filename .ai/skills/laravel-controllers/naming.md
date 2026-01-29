# Controller Naming Conventions

The `{Action}{Resource}Controller` pattern applies across the stack. **Use whatever verb best describes the action** - the examples below are common patterns, not requirements.

## Common Action Verbs

These are frequently used verbs for CRUD operations:

| Verb | Purpose | HTTP Verb | Returns |
|------|---------|-----------|---------|
| `Index` | List resources | GET | Inertia page with collection |
| `Show` | Display single resource | GET | Inertia page with resource |
| `Create` | Show creation form/drawer | GET | Opens drawer, dispatches to Index |
| `Store` | Process creation | POST | Redirect back |
| `Edit` | Show edit form/drawer | GET | Opens drawer, dispatches to Show |
| `Update` | Process update | PATCH/PUT | Redirect back |
| `Destroy` | Delete resource | DELETE | Redirect back |

## Other Useful Verbs

Use any verb that clearly describes the action:

| Verb | Purpose | Example |
|------|---------|---------|
| `Search` | Search/autocomplete | `SearchPostsController` |
| `Export` | Export data | `ExportPostsController` |
| `Import` | Import data | `ImportPostsController` |
| `Sync` | Sync relationships | `SyncPostTagsController` |
| `Publish` | Publish draft | `PublishPostController` |
| `Archive` | Soft archive | `ArchivePostController` |
| `Restore` | Restore from archive | `RestorePostController` |
| `Clone` | Duplicate resource | `ClonePostController` |
| `Approve` | Approve pending item | `ApproveCommentController` |
| `Reject` | Reject pending item | `RejectCommentController` |

## Naming Formula

```
{Action}{Resource}Controller
```

### Single Resource Examples

| Controller | Route | View |
|------------|-------|------|
| `ShowPostController` | `post.show` | `Posts/ShowPost` |
| `CreatePostController` | `post.create` | Opens drawer |
| `StorePostController` | `post.store` | N/A (redirect) |
| `EditPostController` | `post.edit` | Opens drawer |
| `UpdatePostController` | `post.update` | N/A (redirect) |
| `DestroyPostController` | `post.destroy` | N/A (redirect) |

### Collection Examples

| Controller | Route | View |
|------------|-------|------|
| `IndexPostsTableController` | `post.index` | `Posts/IndexPostsTable` |
| `SearchPostsController` | `post.search` | `Posts/SearchPosts` |
| `ExportPostsController` | `post.export` | N/A (download) |

## Multi-Word Resources

Use PascalCase for multi-word resources:

```
ShowBlogPostController           → blog-post.show
IndexUserProfilesController      → user-profile.index
StoreShippingAddressController   → shipping-address.store
```

## Nested Resources

Sub-group controllers in directories:

```
app/Http/Controllers/
├── ShowPostController.php
├── Post/
│   ├── ShowCommentController.php
│   ├── EditCommentController.php
│   └── UpdateCommentController.php
└── Post/
    ├── ShowAttachmentController.php
    └── DestroyAttachmentController.php
```

Route naming for nested resources:

```php
Route::get('posts/{post}/comments/{comment}', ShowCommentController::class)
    ->name('post.comment.show');
```

## Relationship Controllers

Pattern: `{Action}{Parent}{Relationship}Controller`

```
EditPostTagsController           → post.tags.edit
SyncPostTagsController           → post.tags.sync
EditUserRolesController          → user.roles.edit
```

## Tab/Section Controllers

For resources with multiple views/tabs:

```
ShowPostOverviewController    → Posts/ShowPostOverview
ShowPostCommentsController    → Posts/ShowPostComments
ShowPostAnalyticsController   → Posts/ShowPostAnalytics
ShowPostSettingsController    → Posts/ShowPostSettings
```

These typically share an abstract base controller.

## Subresource Controllers

```
CreatePostCommentController   → post.comment.create
StorePostCommentController    → post.comment.store
EditPostCommentController     → post.comment.edit
UpdatePostCommentController   → post.comment.update
DestroyPostCommentController  → post.comment.destroy
```

## Special Action Controllers

| Controller | Purpose |
|------------|---------|
| `ExportPostsController` | Generate export file |
| `ImportPostsController` | Process import file |
| `SyncPostTagsController` | Sync many-to-many |
| `SearchPostsController` | Autocomplete/search |
| `ClonePostController` | Duplicate resource |
| `ArchivePostController` | Soft archive |
| `RestorePostController` | Restore from archive |
| `PublishPostController` | Publish draft |

## Route Name Conventions

```
{singular-resource}.{action}
{singular-resource}.{sub-resource}.{action}
```

Examples:
```
post.index
post.show
post.create
post.store
post.comment.create
post.comment.store
post.tags.edit
user.profile.show
```
