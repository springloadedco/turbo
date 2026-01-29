<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostPublishedNotification;
use App\Repositories\UserRepository;
use App\Services\ToastService;

class StorePostController extends Controller
{
    public function __invoke(
        StorePostRequest $request,
        ToastService $toast,
        UserRepository $userRepository
    ) {
        // Create the post
        $post = new Post($request->validated());
        $request->user()->posts()->save($post);

        // Sync relationships
        $post->tags()->sync($request->validated('tag_ids', []));

        // Notify subscribers if published
        if ($post->is_published) {
            $userRepository
                ->getSubscribersForCategory($post->category_id)
                ->each(fn (User $user) => $user->notify(new PostPublishedNotification($post)));
        }

        // Show success toast
        $toast->success('Post created successfully.');

        return redirect()->route('post.show', $post);
    }
}
