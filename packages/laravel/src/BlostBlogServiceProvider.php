<?php

declare(strict_types=1);

namespace Corpuscom\Blost\LaravelBlog;

use Corpuscom\Blost\Laravel\BlostServiceProvider;
use Illuminate\Support\ServiceProvider;

final class BlostBlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blost-blog.php', 'blost-blog');
        $this->app->register(BlostServiceProvider::class);
        if ($this->app['config']->get('blost.publisher') === null) {
            $this->app['config']->set('blost.publisher', ArticlePublisher::class);
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blost-blog');
        $this->publishes([__DIR__.'/../config/blost-blog.php' => config_path('blost-blog.php')], 'blost-blog-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/blost-blog')], 'blost-blog-views');
        $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'blost-blog-migrations');
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
