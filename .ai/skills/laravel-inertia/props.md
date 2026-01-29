# Props and TypeScript Patterns

## Base Types

### Page Interface

All page components extend the base Page interface:

```tsx
export interface Page {
  meta: {
    title: string;
    description?: string;
    [key: string]: any;
  };
}
```

### PaginatedResource

For paginated collections from Laravel:

```tsx
export interface PaginatedResource<T> {
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
    links: Array<{
      url: string | null;
      label: string;
      active: boolean;
    }>;
    path: string;
    per_page: number;
    to: number;
    total: number;
  };
}
```

## Resource Types

### Full Resource (for show pages)

```tsx
export interface Artist {
  id: string;
  name: string;
  bio?: string | null;
  website?: string | null;
  community?: Community;
  profile_media?: Media | null;
  contact_relationships?: ContactRelationship[];
  platform_profiles?: PlatformProfile[];
  created_at: string;
  updated_at: string;
}
```

### Index Resource (for table rows)

Lighter weight for list views:

```tsx
export interface IndexArtist {
  id: string;
  name: string;
  community?: string | null;
  nettwerk_label_status: EnumCase;
  profile_media?: { id: string; final_url?: string } | null;
  tags: string[];
}
```

### EnumCase Type

For Laravel enum values:

```tsx
export interface EnumCase {
  label: string;
  value: string;
}
```

## Props Patterns

### Show Page Props

```tsx
interface Props extends Page {
  artist: Artist;
  activities?: ActivityLog[];
}
```

### Index Page Props

```tsx
interface Props extends Page {
  artists: PaginatedResource<IndexArtist>;
  communities?: Community[];  // Deferred props are optional
  tags?: Tag[];
}
```

### Tab Page Props

Extend a shared layout props interface:

```tsx
export interface ShowArtistLayoutProps extends Page {
  artist: Artist;
  activities?: ActivityLog[];
  props?: Record<string, any>;
}

interface Props extends ShowArtistLayoutProps {
  memberContactRelationships: ContactRelationship[];
  teamContactRelationships: ContactRelationship[];
}
```

### Drawer Props

```tsx
interface Props {
  // Resource being edited (optional for create)
  artist?: Artist;

  // Related data for form options
  communities: Community[];

  // Required callbacks
  onClose: () => void;

  // Optional redirect after submit
  redirectUrl?: string;
}
```

## Form Data Types

Define separate interface for form state:

```tsx
interface FormData {
  name: string;
  community_id: string | null;
  profile_media_id: string | null;
  redirect_url?: string;
}
```

Nullable fields use `| null`:

```tsx
interface FormData {
  title: string;           // Required string
  description: string | null;  // Optional/nullable
  category_id: string | null;  // Select that can be cleared
  tags: string[];          // Array of IDs
}
```

## Conditional/Optional Props

### Optional with Deferred Loading

```tsx
interface Props extends Page {
  // Always present
  artists: PaginatedResource<IndexArtist>;

  // May be deferred (loaded after initial render)
  communities?: Community[];
  tags?: Tag[];
}
```

### Conditional Based on Type

```tsx
interface Props extends Page {
  product: Product;

  // Only present for certain product types
  physicalDetails?: PhysicalProductDetails;
  digitalAssets?: DigitalAsset[];
}
```

## Common Type Patterns

### Media Type

```tsx
export interface Media {
  id: string;
  file_name: string;
  mime_type: string;
  size: number;
  final_url?: string;
  thumbnail_url?: string;
}
```

### Contact/User Type

```tsx
export interface Contact {
  id: string;
  display_name: string;
  email?: string;
  avatar_url?: string;
}

export interface User {
  id: string;
  name: string;
  email: string;
  avatar?: Media;
}
```

### Relationship Type

```tsx
export interface ContactRelationship {
  id: string;
  contact: Contact;
  relationship: EnumCase;
  priority?: number;
}
```

## Type Utilities

### Pick for Partial Types

```tsx
// Only include specific fields
type ContactPreview = Pick<Contact, 'id' | 'display_name'>;
```

### Omit for Modified Types

```tsx
// Exclude certain fields
type CreateArtistData = Omit<Artist, 'id' | 'created_at' | 'updated_at'>;
```

### Partial for Optional Fields

```tsx
// All fields optional
type ArtistUpdate = Partial<Artist>;
```

## API Resource to TypeScript Mapping

Laravel API Resource → TypeScript interface:

```php
// PHP Resource
return [
    'id' => $this->hash_id,
    'name' => $this->name,
    'community' => new CommunityResource($this->whenLoaded('community')),
    'tags' => TagResource::collection($this->whenLoaded('tags')),
];
```

```tsx
// TypeScript Interface
interface Artist {
  id: string;
  name: string;
  community?: Community;  // whenLoaded = optional
  tags?: Tag[];           // whenLoaded = optional
}
```

## Generic Components

### Table Column Type

```tsx
interface Column<T> {
  label: string;
  key: string;
  isDefault?: boolean;
  className?: string;
  getCellContent: (row: T) => React.ReactNode;
}
```

### Filter Type

```tsx
interface Filter {
  key: string;
  label: string;
  type: 'text' | 'select' | 'multiselect' | 'date';
  options?: Array<{ label: string; value: string }>;
}
```
