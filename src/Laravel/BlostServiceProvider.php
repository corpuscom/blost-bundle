<?php

declare(strict_types=1);

namespace Corpuscom\Blost\Laravel;

use Corpuscom\Blost\Contract\ArticlePublisher;
use Corpuscom\Blost\Http\BlostApi;
use Illuminate\Support\ServiceProvider;

final class BlostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/blost.php', 'blost');
        $this->app->bind(ArticlePublisher::class, function ($app): ArticlePublisher {
            $publisher = $app['config']->get('blost.publisher');
            if (!is_string($publisher) || $publisher === '') {
                throw new \LogicException('Configure blost.publisher with an ArticlePublisher implementation.');
            }

            return $app->make($publisher);
        });
        $this->app->singleton(BlostApi::class, fn($app) => new BlostApi(
            $app->make(ArticlePublisher::class),
            (string) $app['config']->get('blost.token', ''),
            (string) $app['config']->get('blost.name', 'Blost connector'),
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../../config/blost.php' => config_path('blost.php')], 'blost-config');
        // Stateless routes: no session or CSRF middleware; the shared API verifies the bearer token.
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
    }
}
