---
name: laravel-validation
description: Laravel Form Request validation patterns. Use when adding validation, creating form requests, writing validation rules, or when user asks to "validate input", "add validation", "create a form request".
allowed-tools: Read, Glob, Grep, Edit, Write, Bash
---

# Laravel Form Request Validation

## Core Pattern

Create a Form Request class for each controller action that accepts user input.

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
        ];
    }
}
```

## Creating Form Requests

Use artisan to generate form requests:

```bash
php artisan make:request StorePostRequest
php artisan make:request UpdatePostRequest
```

## Naming Convention

**Pattern:** `{Action}{Resource}Request`

| Action | Request Name |
|--------|--------------|
| Store | `StorePostRequest` |
| Update | `UpdatePostRequest` |
| Index | `IndexPostsRequest` |
| Show | `ShowPostRequest` |
| Destroy | `DestroyPostRequest` |

## Authorization

### Policy-Based Authorization

```php
public function authorize(): bool
{
    // Check policy ability
    return $this->user()->can('update', $this->post);
}
```

### Permission-Based Authorization

```php
use App\Enums\Permission;

public function authorize(): bool
{
    return $this->user()->can(Permission::CREATE_POST);
}
```

### Combined Authorization

```php
public function authorize(): bool
{
    return $this->user()->id === $this->comment->user_id
        || $this->user()->can(Permission::DELETE_ANY_COMMENT);
}
```

## Validation Rules

For validation rule syntax and available rules, use Laravel Boost's documentation search - it provides comprehensive, up-to-date Laravel validation documentation.

### Accessing Validated Data

In controllers:

```php
$data = $request->validated();
```
