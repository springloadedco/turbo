<?php

namespace App\Http\Controllers;

use App\Http\QueryBuilders\IndexPostsQueryBuilder;
use App\Http\Requests\IndexPostsRequest;
use App\Http\Resources\IndexPostResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TagResource;
use App\Models\Category;
use App\Models\Tag;
use App\Services\PerPagePreference;
use Inertia\Inertia;
use Inertia\Response;

class IndexPostsTableController extends Controller
{
    public function __invoke(
        IndexPostsRequest $request,
        IndexPostsQueryBuilder $query,
        PerPagePreference $perPage
    ): Response {
        // Paginate with query string preservation
        $posts = $query
            ->paginate($perPage->remember())
            ->withQueryString();

        // Load filter options
        $categories = Category::whereNull('parent_id')
            ->with('children')
            ->get();
        $tags = Tag::query()->get();

        return Inertia::render('Posts/IndexPostsTable', [
            'meta' => [
                'title' => 'Posts',
            ],
            'posts' => IndexPostResource::collection($posts),
            // Defer filter options - not needed on initial render
            'categories' => Inertia::defer(
                fn () => CategoryResource::collection($categories),
                'filterOptions'
            ),
            'tags' => Inertia::defer(
                fn () => TagResource::collection($tags),
                'filterOptions'
            ),
        ]);
    }
}
