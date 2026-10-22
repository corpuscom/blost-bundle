<?php

declare(strict_types=1);

use Corpuscom\Blost\LaravelBlog\BlogController;
use Illuminate\Support\Facades\Route;

// With public pages disabled, the host must supply the named detail route.
Route::prefix(config('blost-blog.prefix', 'blog'))->group(function (): void {
    if (config('blost-blog.public', true)) {
        Route::get('/', [BlogController::class, 'index'])->name('blost.blog.index');
        Route::get('/{id}', [BlogController::class, 'show'])->whereUuid('id')->name('blost.blog.show');
    }
});
