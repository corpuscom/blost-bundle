<?php

declare(strict_types=1);

namespace Corpuscom\Blost\LaravelBlog;

use Illuminate\Contracts\View\View;

final class BlogController
{
    public function index(): View
    {
        $posts = Post::query()->where('published_at', '<=', gmdate('Y-m-d H:i:s'))
            ->orderByDesc('published_at')->orderBy('id')->simplePaginate(20);

        return view('blost-blog::index', ['posts' => $posts]);
    }

    public function show(string $id): View
    {
        $post = Post::query()->where('published_at', '<=', gmdate('Y-m-d H:i:s'))->findOrFail($id);

        return view('blost-blog::show', ['post' => $post]);
    }
}
