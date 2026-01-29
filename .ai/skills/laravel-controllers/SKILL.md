---
name: laravel-controllers
description: Laravel invokable controller patterns with Inertia. Use when creating endpoints, adding pages, building CRUD, or when user asks to "create an endpoint", "add a controller", "build a page", "add a store action", "create show page".
allowed-tools: Read, Glob, Grep, Edit, Write, Bash
---

# Laravel Invokable Controllers

## The Naming Pattern

Use `{Action}{Resource}` consistently across the entire stack:

| Layer | Pattern | Example |
|-------|---------|---------|
| Controller | `{Action}{Resource}Controller` | `StorePostController` |
| Form Request | `{Action}{Resource}Request` | `StorePostRequest` |
| Route name | `{resource}.{action}` | `post.store` |
| Page view | `Pages/{Resource}/{ActionResource}.tsx` | `Pages/Posts/ShowPost.tsx` |

**Action verbs are suggestions.** Common CRUD actions use Index, Show, Create, Store, Edit, Update, Destroy - but use whatever verb fits your situation: `PublishPostController`, `ArchiveCommentController`, `SyncPostTagsController`, `ImportUsersController`.

## Creating Files with Artisan

Use artisan commands to generate files in the correct locations:

```bash
# Create invokable controller
php artisan make:controller ShowPostController --invokable

# Create form request
php artisan make:request ShowPostRequest
```

## Creating an Endpoint

When creating a new endpoint, create the full stack of files together.

### Example: Show Post (GET → Render Page)

```
GET /posts/{post} → ShowPostController
                  → ShowPostRequest
                  → route('post.show')
                  → Pages/Posts/ShowPost.tsx
```

**1. Generate files:**
```bash
php artisan make:controller ShowPostController --invokable
php artisan make:request ShowPostRequest
```

**2. Route** (`routes/web.php`):
```php
Route::get('posts/{post}', ShowPostController::class)->name('post.show');
```

**3. Controller:**
```php
class ShowPostController extends Controller
{
    public function __invoke(ShowPostRequest $request, Post $post): Response
    {
        return Inertia::render('Posts/ShowPost', [
            'meta' => ['title' => $post->title],
            'post' => new PostResource($post),
        ]);
    }
}
```

**4. Request:**
```php
class ShowPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->post);
    }

    public function rules(): array
    {
        return [];
    }
}
```

**5. View** (`resources/js/Pages/Posts/ShowPost.tsx`):
```tsx
interface Props extends Page {
  post: Post;
}

const ShowPost = ({ post }: Props) => {
  return <div>{post.title}</div>;
};

ShowPost.layout = (page: any) => (
  <Authenticated children={page} meta={page.props.meta} />
);

export default ShowPost;
```

### Example: Store Post (POST → Redirect)

```
POST /posts → StorePostController
            → StorePostRequest
            → route('post.store')
            → (redirects back)
```

**1. Generate files:**
```bash
php artisan make:controller StorePostController --invokable
php artisan make:request StorePostRequest
```

**2. Route:**
```php
Route::post('posts', StorePostController::class)->name('post.store');
```

**3. Controller:**
```php
class StorePostController extends Controller
{
    public function __invoke(StorePostRequest $request)
    {
        $post = Post::create($request->validated());

        return redirect()->route('post.show', $post);
    }
}
```

**4. Request:**
```php
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
```

## Controller Patterns

### Read Actions (GET → Render Inertia Page)

```php
public function __invoke(ShowPostRequest $request, Post $post): Response
{
    return Inertia::render('Posts/ShowPost', [
        'meta' => ['title' => $post->title],
        'post' => new PostResource($post),
    ]);
}
```

### Write Actions (POST/PATCH/DELETE → Redirect)

```php
public function __invoke(UpdatePostRequest $request, Post $post)
{
    $post->update($request->validated());

    return redirect()->back();
}
```

## Route Definition

```php
Route::middleware('auth')->group(function () {
    Route::get('posts', IndexPostsTableController::class)->name('post.index');
    Route::post('posts', StorePostController::class)->name('post.store');
    Route::get('posts/{post}', ShowPostController::class)->name('post.show');
    Route::patch('posts/{post}', UpdatePostController::class)->name('post.update');
    Route::delete('posts/{post}', DestroyPostController::class)->name('post.destroy');
});
```

## Dependency Injection

Inject services in `__invoke()` parameters:

```php
public function __invoke(
    StorePostRequest $request,         // Form Request (auto-validated)
    Post $post                         // Route model binding
)
```

## See Also

- `naming.md` - Common action verbs (suggestions, use what fits your situation)
- `examples/` - Full controller examples
