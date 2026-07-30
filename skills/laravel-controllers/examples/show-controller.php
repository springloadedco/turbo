<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowPostRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\CommentResource;
use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

class ShowPostController extends Controller
{
    public function __invoke(ShowPostRequest $request, Post $post): Response
    {
        $post->load([
            'author',
            'comments.user',
            'category',
            'tags',
        ]);

        return Inertia::render('Posts/ShowPost', [
            'meta' => [
                'title' => $post->title,
            ],
            'post' => new PostResource($post),
            'comments' => CommentResource::collection($post->comments),
        ]);
    }
}
