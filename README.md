# Blost integration for Symfony and Laravel

Receive articles from [Blost](https://blost.ai) in your existing Symfony or Laravel application. One Composer package provides a Symfony bundle and a Laravel service provider with the same authenticated publishing API.

For a ready-to-use blog with database storage and public pages, choose the optional [Laravel integration](packages/laravel/README.md) or [Symfony integration](packages/symfony/README.md). These packages provide the publisher for you. All three packages are available on Packagist; see the [package guide](docs/packages.md) for package choices and development.

The base package handles authentication, validation, and delivery to your publisher. Your application saves articles into its own CMS and supplies the public pages, media handling, and SEO rendering.

[Installation](#1-install-from-packagist) · [Publisher](#2-implement-your-article-publisher) · [Laravel](#laravel) · [Symfony](#symfony) · [Connect in Blost](#4-connect-your-application-in-blost) · [Troubleshooting](#troubleshooting)

## Requirements

- PHP 8.2 or later, subject to your framework's PHP requirements.
- Symfony 6.4, 7, or 8; or Laravel 12 or 13.
- A public HTTPS URL for your application, on port 443, reachable from Blost.

Framework dependencies are optional: installing in Laravel does not install Symfony FrameworkBundle, and installing in Symfony does not install Laravel.

## 1. Install from Packagist

The package is available on [Packagist](https://packagist.org/packages/corpuscom/blost-bundle). Until a tagged release is available, install the `main` development branch explicitly:

```sh
composer require corpuscom/blost-bundle:dev-main
```

No custom Composer repository or project-wide `minimum-stability` change is needed. Once tagged releases are available, use `composer require corpuscom/blost-bundle` for a stable release.

Generate a token:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Add the generated value to your application's environment configuration:

```dotenv
BLOST_TOKEN=replace_with_the_generated_token
```

Use a different token for each installation. An empty token denies every request. Keep the token on the server and enter the same value in Blost when connecting.

## 2. Implement your article publisher

Create `App\Blost\ArticlePublisher` implementing `Corpuscom\Blost\Contract\ArticlePublisher`. The package calls this method whenever Blost publishes or updates an article:

```php
public function publish(\Corpuscom\Blost\Article $article): \Corpuscom\Blost\PublishedArticle;
```

Start with the adapter for your application's persistence layer:

- [Laravel / Eloquent example](examples/Laravel/ArticlePublisher.php).
- [Symfony / Doctrine ORM example](examples/Symfony/ArticlePublisher.php).

The examples assume your own `BlogPost` model/entity and public article route. Adapt their field names and route names to your CMS before registering the publisher. Doctrine is only needed if you use the Doctrine example.

Use `$article->id` as the external identity and enforce a **unique database index** on that field. For example, an Eloquent migration for an existing `blog_posts` table can add it with:

```php
Schema::table('blog_posts', function (Blueprint $table) {
    $table->uuid('blost_id')->nullable()->unique();
});
```

Import `Illuminate\Support\Facades\Schema` and `Illuminate\Database\Schema\Blueprint` in that migration. Add the remaining fields required by your model or map them to existing CMS columns.

Save these values from the incoming `Article`:

| Property | Usage |
| --- | --- |
| `id` | Stable Blost UUID; atomically create or update the matching article. |
| `title` | Article title. |
| `content` | Full editorial HTML, including headings, links, and optional structured data. |
| `excerpt` | Optional short description. |
| `metaTitle` | Optional SEO title. |
| `metaDescription` | Optional SEO description. |
| `imageUrl` | Optional HTTP(S) image URL; import through your media pipeline or store the URL. |
| `language` | Optional Blost language label, such as `Hungarian`; not necessarily an ISO code. |
| `publishedAt` | Optional `DateTimeImmutable` publication timestamp. |

Optional null metadata clears the stored value. For a null publication timestamp, preserve the existing first publication date or use the current time for a new article.

Return `new PublishedArticle($publicUrl)` only after durable persistence, using the article's real absolute HTTP(S) URL. Throw on storage failure so Blost receives a 5xx and can retry. Handle concurrent creates with an upsert or unique-conflict retry: a transaction alone does not prevent duplicate records.

Preserve the HTML according to your CMS's editorial content policy. The package passes image URLs to your adapter without downloading them; apply your existing destination validation and download limits if importing images. Scope writes to the intended blog or tenant in your adapter.

## 3. Register with your framework

### Laravel

The service provider is discovered automatically. Publish the configuration:

```sh
php artisan vendor:publish --tag=blost-config
```

In `config/blost.php`, set your publisher class:

```php
return [
    'token' => env('BLOST_TOKEN', ''),
    'name' => env('APP_NAME', 'Blost connector'),
    'publisher' => App\Blost\ArticlePublisher::class,
];
```

Confirm that the routes are registered:

```sh
php artisan route:list --path=blost/v1
```

The provider registers stateless routes outside the `web` middleware group; they authenticate with the bearer token. The routes support `route:cache`. If you cache configuration in production, rebuild it after changing the token or publisher:

```sh
php artisan config:cache
```

### Symfony

Add the bundle entry to the array in `config/bundles.php`:

```php
Corpuscom\Blost\Symfony\BlostBundle::class => ['all' => true],
```

Create `config/packages/blost.yaml`:

```yaml
blost:
    token: '%env(BLOST_TOKEN)%'
    name: 'My website'
    publisher: App\Blost\ArticlePublisher
```

Create `config/routes/blost.yaml`:

```yaml
blost:
    resource: '@BlostBundle/config/routes.php'
    type: php
```

Register your publisher as a service. Standard `App\` service discovery covers it when the class lives in `src/Blost/ArticlePublisher.php`.

If your firewall requires a login, add the following rule before the catch-all entry in `security.access_control`:

```yaml
- { path: ^/blost/v1/, roles: PUBLIC_ACCESS }
```

Ensure your firewall's authenticators allow these requests to reach the controller. The package verifies the bearer token itself.

Check the routes and rebuild the production cache after deployment:

```sh
php bin/console debug:router blost_status
php bin/console debug:router blost_publish
php bin/console cache:clear --env=prod
```

## 4. Connect your application in Blost

Use a Blost deployment that includes the native Symfony / Laravel connector. Open **Settings → CMS connection → Symfony / Laravel** and enter:

- **Connector URL:** `https://your-site.example/blost/v1`. Include any application subdirectory, for example `https://your-site.example/app/blost/v1`.
- **Token:** the same value as your application's `BLOST_TOKEN`.

Choose **Test connection**, then **Save connection**. Blost encrypts the token at rest and does not return it to the browser. Leave the token blank on later saves to keep the saved value.

Publish a test article, edit it, and republish. Confirm that your CMS contains one article with the updated content and that its public page shows the expected image and SEO metadata. Then verify scheduled delivery before enabling unattended publishing.

Manual publishing, edits, scheduled publishing, and auto-publishing use the same article UUID. Drafts waiting for approval remain in Blost until published. If a request times out or Blost cannot record a successful delivery, retrying updates the same CMS article.

## Verify the API directly

With `BLOST_TOKEN` exported in your shell, test your deployed connector:

```sh
curl --fail-with-body \
  --header "Authorization: Bearer $BLOST_TOKEN" \
  https://your-site.example/blost/v1/status
```

Loading a `.env` file in PHP does not automatically export its values into your terminal. Set the shell variable separately before running the command.

A successful connection returns HTTP 200 with the configured name:

```json
{"protocol_version":"1","name":"My website"}
```

Publishing uses `PUT /blost/v1/articles/{uuid}` with a JSON payload. See [the API contract](docs/protocol.md) for request examples, response fields, and limits.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Composer cannot find a stable version | Install `corpuscom/blost-bundle:dev-main` until a tagged release is available. |
| HTTP 401 | Match the token in Blost and the receiving application; refresh cached configuration and ensure your proxy forwards `Authorization` to PHP. |
| HTTP 404 | Register the framework routes and include any application subdirectory in the connector URL. |
| Login page or redirect instead of JSON | Allow the connector routes through your application firewall and use their final HTTPS URL. Blost does not follow redirects. |
| HTTP 413 | Requests are limited to 2 MiB. Set proxy/PHP limits to at least that value. |
| HTTP 422 | Check the UUID and payload types. Image data URIs are unsupported; provide an HTTP(S) image URL. |
| HTTP 5xx | Check the publisher configuration and application logs for storage or mapping errors. |
| Connection fails for a local address | Blost requires a public HTTPS endpoint. Use a public HTTPS tunnel for local development. |

## Contributing

See [CONTRIBUTION.md](CONTRIBUTION.md) for development setup, supported dependency combinations, validation commands, and pull-request guidance. Automated agents should also read [AGENTS.md](AGENTS.md).
