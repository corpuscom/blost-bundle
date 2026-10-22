# Blost integration for Symfony and Laravel

One Composer package receives articles from [Blost](https://github.com/corpuscom/blost) through a native, versioned API. It includes a Symfony bundle and a Laravel service provider with automatic package discovery. Both use the same authentication, validation, and publishing contract.

Requires PHP 8.2+, Symfony 6.4/7/8 or Laravel 12/13. Framework dependencies are optional at runtime: installing in Laravel does not install Symfony FrameworkBundle, and installing in Symfony does not install Laravel.

## Install

```sh
composer require corpuscom/blost-bundle
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

The package must first be published to a Composer repository. Before publication, add this checkout as a Composer `path` repository and require `corpuscom/blost-bundle:@dev`.

Put the generated token in your application's environment as `BLOST_TOKEN`. Use a different token for each installation. An empty token denies every request.

## Symfony

Register the bundle in `config/bundles.php`:

```php
Corpuscom\Blost\Symfony\BlostBundle::class => ['all' => true],
```

Configure `config/packages/blost.yaml`:

```yaml
blost:
    token: '%env(BLOST_TOKEN)%'
    name: 'My website'
    publisher: App\Blost\ArticlePublisher
```

Import the package routes in `config/routes/blost.yaml`:

```yaml
blost:
    resource: '@BlostBundle/config/routes.php'
    type: php
```

Register your publisher as a service (the usual `App\` service discovery handles this). If your security firewall requires a login, place `- { path: ^/blost/v1/, roles: PUBLIC_ACCESS }` before the catch-all access rule and make sure a firewall authenticator does not reject these requests first. The package itself authenticates every request using the token.

See `examples/Symfony/ArticlePublisher.php` for a Doctrine ORM adapter.

## Laravel

The provider is discovered automatically. Publish its config:

```sh
php artisan vendor:publish --tag=blost-config
```

Set `publisher` in `config/blost.php` to your implementation:

```php
'publisher' => App\Blost\ArticlePublisher::class,
```

The provider registers stateless routes outside the `web` middleware group. They use bearer authentication and support `route:cache` and `config:cache`. Read the environment only through config. After changing config or the token, rebuild your production config cache.

See `examples/Laravel/ArticlePublisher.php` for an Eloquent adapter.

## Connect in Blost

Deploy the accompanying Blost feature branch, then open **Settings → CMS connection → Symfony / Laravel**. Enter:

- Connector URL: `https://your-site.example/blost/v1` (include a subdirectory if the app is installed under one).
- Token: the same value as `BLOST_TOKEN`.

Test and save the connection. Blost encrypts the token at rest and never returns it to the browser. Manual publishing, edits to published articles, scheduled publishing, and auto-publishing use the same article UUID. No Blost database migration is required. Drafts waiting for approval remain in Blost until published.

## Save articles in your application

Implement `Corpuscom\Blost\Contract\ArticlePublisher`:

```php
public function publish(\Corpuscom\Blost\Article $article): \Corpuscom\Blost\PublishedArticle;
```

Your adapter must atomically upsert using `$article->id`, enforced by a **unique database index**. A UUID identifies the same article across requests, including retries after a timeout or a Blost bookkeeping failure. Do not insert a new record for every request. Handle concurrent creates with an upsert or a unique-conflict retry; a transaction alone does not prevent duplicate creation.

Map the title, full HTML body, excerpt, SEO title/description, image URL, language, and publication date to your application's own fields. Optional null metadata clears the stored value. A null publication date means use the existing first publication date, or the current time for a new article. Return the article's real public HTTP(S) URL only after saving successfully; throw on storage failure so Blost receives a 5xx and can retry.

The package preserves editorial HTML, including headings, links, and structured data. Apply your CMS's existing content policy when saving and rendering it. Image URLs are passed to your adapter without fetching remote resources. You can store the URL or import the image through your application's existing media pipeline; validate destinations and bound downloads if importing. Scope writes to this installation's designated blog/tenant; never infer a tenant from arbitrary payload fields.

The package supplies the publishing bridge. Your application supplies persistence, public pages, media handling, and SEO rendering using its existing CMS.

See [the API contract](docs/protocol.md) for payloads, limits, and deployment checks.

## Development

```sh
composer install
composer check
```

Tests boot a Symfony kernel and a Laravel application/router, exercise both adapters, and verify authentication, payload rejection, rich-content preservation, and repeated updates. CI covers Symfony 6.4 alone, Symfony 7/Laravel 12, and Symfony 8/Laravel 13. Symfony 6.4 and Laravel 12 require different major versions of HttpKernel, so they are tested in separate dependency environments.
