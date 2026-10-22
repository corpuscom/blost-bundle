# Blost for Symfony

A ready-to-use blog for Symfony 6.4, 7, or 8, built on [the shared Blost publishing API](https://github.com/corpuscom/blost-bundle). It includes a mapped Doctrine entity, publisher, public routes, and Twig pages. PHP must meet the selected Symfony and Doctrine versions' requirements; the package source supports PHP 8.2.

## Install

Install from [Packagist](https://packagist.org/packages/corpuscom/blost-symfony) in your application directory. Until tagged releases are available, require both the integration and its shared dependency explicitly at `dev-main`:

```sh
composer require corpuscom/blost-symfony:dev-main corpuscom/blost-bundle:dev-main
```

No repository clone, custom Composer repository, or project-wide `minimum-stability` change is needed. The explicit shared dependency allows Composer to install its development version while keeping your application's normal stability policy.

If you used the previous local checkout instructions, remove that path repository with `composer config --unset repositories.blost-symfony` before running the installation command.

Ensure these bundles are registered in `config/bundles.php`; Symfony Flex may already register the Doctrine and Twig bundles:

```php
Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
Corpuscom\Blost\Symfony\BlostBundle::class => ['all' => true],
Corpuscom\Blost\SymfonyBlog\BlostBlogBundle::class => ['all' => true],
```

Generate a token with `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` and set `BLOST_TOKEN` in your application's environment. The token is read automatically; no publisher service or `blost.yaml` is required. An existing `blost.publisher` configuration takes precedence over the package default.

Configure your normal Doctrine database connection and migrations path if your application does not already have them:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
    orm: {}
```

```yaml
# config/packages/doctrine_migrations.yaml
doctrine_migrations:
    migrations_paths:
        DoctrineMigrations: '%kernel.project_dir%/migrations'
```

The package registers its entity mapping and UTC timestamp type automatically with the default entity manager. Create `config/routes/blost.yaml`:

```yaml
blost:
    resource: '@BlostBlogBundle/config/routes.php'
    type: php
```

This imports both the connector and public routes. Replace an existing import of `@BlostBundle/config/routes.php` with this one to avoid duplicate imports.

Generate a migration for your database platform, review all proposed schema changes, then apply it:

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console debug:router blost_publish
php bin/console debug:router blost_blog_show
```

No MakerBundle or application entity is required. The package entity is `Corpuscom\Blost\SymfonyBlog\Entity\Post`, stored in a dedicated `blost_posts` table. Do not map an unrelated existing table to that name. The generated migration belongs to your application and includes any other outstanding mapping changes; review it before running it.

If your firewall requires login, permit anonymous requests to the connector and blog routes before catch-all access rules, and ensure your authenticators allow them to reach the controllers:

```yaml
# entries in security.access_control
- { path: ^/blost/v1/, roles: PUBLIC_ACCESS }
- { path: ^/blog(?:/|$), roles: PUBLIC_ACCESS }
```

The connector verifies its own bearer token. Configure your trusted proxy/host settings so generated absolute URLs use your public HTTPS domain.

In [Blost](https://blost.ai), connect the Symfony / Laravel connector to `https://your-site.example/blost/v1` using the same token. Publish an article and visit its returned URL, normally `https://your-site.example/blog/{uuid}`. See the [connection guide](https://github.com/corpuscom/blost-bundle#4-connect-your-application-in-blost).

## Customize

Optional configuration:

```yaml
# config/packages/blost_blog.yaml
blost_blog:
    prefix: /news
    title: Company news
    public: true
```

Update any firewall rules when changing the prefix. Override templates in `templates/bundles/BlostBlogBundle/`: `layout.html.twig`, `index.html.twig`, and `show.html.twig`. They escape metadata and render editorial HTML directly. The stored language is Blost's original label; the HTML document uses the request locale. Images reference the supplied URL without server-side downloads.

Named routes are `blost_blog_index` and `blost_blog_show` (parameter `id`). To render pages yourself, set `public` to false and supply your own `blost_blog_show` route before publishing. The built-in publisher uses that named route for absolute URLs. You can also configure an application publisher through `blost.publisher`.

Pages list 20 posts at a time. Future-dated posts remain hidden until their publication time. Read [the package behavior guide](https://github.com/corpuscom/blost-bundle/blob/main/docs/packages.md) for persistence and concurrency semantics. Do not wrap connector requests in an application transaction: the publisher must commit before acknowledging success.

Rebuild your production cache after configuration, template, or route changes:

```sh
php bin/console cache:clear --env=prod
```

## Development

Make changes in [corpuscom/blost-bundle](https://github.com/corpuscom/blost-bundle). This repository is a distribution mirror, automatically synchronized from the monorepo's `packages/symfony` directory on `main`. Submit code changes and pull requests to the monorepo.
