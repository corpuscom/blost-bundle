# Blost for Laravel

A ready-to-use blog for Laravel 12 or 13, built on [the shared Blost publishing API](https://github.com/corpuscom/blost-bundle). It includes an Eloquent model, a publishable migration, the article publisher, and public Blade pages. PHP must meet your Laravel version's requirements.

## Install

This optional package is not yet separately published on Packagist. For now, clone the monorepo next to your application, then run the Composer commands from your application directory:

```sh
git clone https://github.com/corpuscom/blost-bundle.git ../blost-bundle
composer config repositories.blost-laravel '{"type":"path","url":"../blost-bundle/packages/laravel","options":{"versions":{"corpuscom/blost-laravel":"dev-main"}}}'
composer require corpuscom/blost-laravel:dev-main corpuscom/blost-bundle:dev-main
```

Composer discovers both providers automatically. No application model, controller, or publisher implementation is required. The model is `Corpuscom\Blost\LaravelBlog\Post`, backed by `blost_posts` on your default database connection. If you already set `blost.publisher`, that implementation takes precedence; remove that setting or set it to null to use the supplied publisher.

Generate a token with `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` and add it to your application's environment:

```dotenv
BLOST_TOKEN=replace_with_generated_token
APP_URL=https://your-site.example
```

Publish the migration, review it, then apply it:

```sh
php artisan vendor:publish --tag=blost-blog-migrations
php artisan migrate
php artisan route:list --path=blost/v1
php artisan route:list --path=blog
```

The migration creates a dedicated table; do not apply it over an existing unrelated `blost_posts` table. Configure your database through the application's usual Laravel configuration.

In [Blost](https://blost.ai), connect the Symfony / Laravel connector to `https://your-site.example/blost/v1` using the same token. Publish an article and visit its returned URL, normally `https://your-site.example/blog/{uuid}`. See the [connection guide](https://github.com/corpuscom/blost-bundle#4-connect-your-application-in-blost).

## Customize

Publish configuration or templates only when you need to change them:

```sh
php artisan vendor:publish --tag=blost-config
php artisan vendor:publish --tag=blost-blog-config
php artisan vendor:publish --tag=blost-blog-views
```

The blog configuration is separate from the connector's token and publisher settings:

```php
// config/blost-blog.php
return [
    'prefix' => 'news',
    'title' => 'Company news',
    'public' => true,
];
```

Templates can be overridden in `resources/views/vendor/blost-blog/`. The package views are `layout`, `index`, and `show`. They escape metadata and render editorial HTML directly. The stored language is Blost's original label; the HTML document uses the application's locale. Image URLs are referenced directly, without server-side downloads.

Named routes are `blost.blog.index` and `blost.blog.show` (parameter `id`). To render pages yourself, set `public` to false and provide your own `blost.blog.show` route before publishing articles. The built-in publisher uses that named route for absolute URLs. You can also configure an application publisher through `blost.publisher`.

Pages list 20 posts at a time. Future-dated posts remain hidden until their publication time. Read [the package behavior guide](https://github.com/corpuscom/blost-bundle/blob/main/docs/packages.md) for persistence and concurrency semantics.

Rebuild production caches after configuration or route changes:

```sh
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The connector uses bearer authentication outside the `web` middleware group. The read-only blog pages are public and stateless. Do not wrap connector requests in a database transaction middleware: the publisher must commit before acknowledging success.

## Development

Make changes in [corpuscom/blost-bundle](https://github.com/corpuscom/blost-bundle). This repository is a distribution mirror, automatically synchronized from the monorepo's `packages/laravel` directory on `main`. Submit code changes and pull requests to the monorepo.
