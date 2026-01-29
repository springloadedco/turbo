---
name: laravel-inertia
description: Inertia.js page component patterns with TypeScript. Use when creating pages or when user asks to "create a page", "build a component".
allowed-tools: Read, Glob, Grep, Edit, Write, Bash
---

# Inertia.js Page Components

## File Organization

```
resources/js/
├── Pages/
│   └── {Resource}/
│       ├── Index{Resources}.tsx      # List view
│       └── Show{Resource}.tsx        # Detail view
├── @types/
│   └── {Resource}.ts                 # TypeScript interfaces
├── components/
│   └── {feature}/                    # Feature components
└── layouts/
    └── {Layout}.tsx                  # Page layouts
```

## Page Component Pattern

Use the `.layout` property for persistent layouts that don't remount between page visits:

```tsx
import React from 'react';
import { Page } from '@/@types/Page';
import { Authenticated } from '@/layouts/Authenticated';

interface Props extends Page {
  post: Post;
}

const ShowPost = ({ post }: Props) => {
  return (
    <div>
      <h1>{post.title}</h1>
      <p>{post.body}</p>
    </div>
  );
};

// Persistent layout - doesn't remount between page visits
ShowPost.layout = (page: any) => (
  <Authenticated children={page} meta={page.props.meta} />
);

export default ShowPost;
```

## Props Interfaces

### Base Page Props

```tsx
// @types/Page.ts
export interface Page {
  meta: {
    title: string;
    [key: string]: any;
  };
}
```

### Resource Props

```tsx
interface Props extends Page {
  post: Post;                              // Single resource
  posts: PaginatedResource<IndexPost>;     // Paginated collection
  categories?: Category[];                 // Optional data
}
```

### Paginated Resource (Laravel Pagination)

```tsx
interface PaginatedResource<T> {
  data: T[];
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
}
```

## TypeScript Interfaces

Define in `@types/{Resource}.ts`:

```tsx
export interface Post {
  id: number;
  title: string;
  body?: string;
  category?: Category;
  created_at: string;
  updated_at: string;
}

export interface IndexPost {
  id: number;
  title: string;
  category?: string;
  status: string;
}
```

## Controller to Component Mapping

| Controller | Component Path |
|------------|----------------|
| `IndexPostsController` | `Pages/Posts/IndexPosts.tsx` |
| `ShowPostController` | `Pages/Posts/ShowPost.tsx` |
| `ShowPostCommentsController` | `Pages/Posts/ShowPostComments.tsx` |

## See Also

- `examples/` - Full component examples
