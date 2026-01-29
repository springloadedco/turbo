import React from 'react';
import { Link } from '@inertiajs/react';
import { Page } from '@/@types/Page';
import { Authenticated } from '@/layouts/Authenticated';
import { show } from '@/actions/App/Http/Controllers/PostController';

/**
 * Props interface extending the base Page props.
 * Add props specific to this page.
 */
interface Props extends Page {
  post: Post;
  relatedPosts: RelatedPost[];
}

interface Post {
  id: number;
  title: string;
  body: string;
  author: {
    name: string;
  };
  published_at: string;
}

interface RelatedPost {
  id: number;
  title: string;
  excerpt: string;
}

/**
 * Page component for viewing a single post.
 * Receives props from ShowPostController.
 */
const ShowPost = ({ post, relatedPosts }: Props) => {
  return (
    <article className="max-w-2xl mx-auto p-4">
      <header className="mb-8">
        <h1 className="text-3xl font-bold">{post.title}</h1>
        <p className="text-gray-600">
          By {post.author.name} on {post.published_at}
        </p>
      </header>

      <div className="prose" dangerouslySetInnerHTML={{ __html: post.body }} />

      {relatedPosts.length > 0 && (
        <aside className="mt-8 pt-8 border-t">
          <h2 className="text-xl font-semibold mb-4">Related Posts</h2>
          <ul className="space-y-2">
            {relatedPosts.map((related) => (
              <li key={related.id}>
                <Link
                  href={show(related.id).url}
                  className="text-blue-600 hover:underline"
                >
                  {related.title}
                </Link>
              </li>
            ))}
          </ul>
        </aside>
      )}
    </article>
  );
};

/**
 * Layout wrapper using persistent layout pattern.
 * The layout persists across page navigations, preserving state.
 */
ShowPost.layout = (page: any) => (
  <Authenticated children={page} meta={page.props.meta} />
);

export default ShowPost;
