# Ready-to-use framework integrations

The repository contains three Composer packages:

| Package | Purpose | Location |
| --- | --- | --- |
| `corpuscom/blost-bundle` | Shared publishing API and lightweight framework adapters for an existing CMS. | Repository root |
| `corpuscom/blost-laravel` | Eloquent storage, publishable migration, publisher, and Blade blog pages. | [Laravel](../packages/laravel/README.md) |
| `corpuscom/blost-symfony` | Doctrine entity and mapping, publisher, migration generation, and Twig blog pages. | [Symfony](../packages/symfony/README.md) |

The base package remains available on Packagist as `dev-main`. The two optional packages have not been published separately yet. Their guides show local Composer path installation from a checkout, without relaxing project-wide stability. After separate publication, a host can require its framework package and the base development dependency explicitly until stable releases exist.

Each optional package depends on the base package. Authentication, payload validation, and the wire protocol have one implementation. Host applications using an existing CMS can keep their current publisher and avoid installing either optional package.

## Defaults and customization

Both blog packages use a dedicated `blost_posts` table with the Blost UUID as its primary key. They store all supported metadata, preserve editorial HTML, clear null metadata, and preserve the first publication date on retries when the sender supplies no date. Dates are stored and read in UTC regardless of the PHP/application timezone. No media is downloaded; the default article page references the supplied image URL.

Public routes default to `GET /blog` and `GET /blog/{id}`. UUID URLs remain stable after title changes. The listing has 20 articles per page, ordered by publication time then UUID. Future-dated articles are hidden from both public routes until their publication time; a successful API response for such an article can therefore point to a page that temporarily returns 404.

Default templates escape titles, excerpts, and metadata while rendering article HTML as trusted editorial content. Installing these templates opts into that content policy. Override them to apply your application's own rendering policy and design. These packages supply no authoring/admin interface, sitemap, feed, deletion API, or media importer.

Database changes are explicit: publish and run the Laravel migration, or generate, review, and run a Doctrine migration. No table is created on application boot. Symfony uses the default entity manager; Laravel uses the default database connection. The two packages are alternatives for separate host applications, not two storage engines for the same installation.

Laravel recovers concurrent creation through Eloquent's unique-conflict handling. Symfony enforces the primary key and lets a concurrent insert conflict return 5xx; Blost can retry against the winning row. Both publishers commit their own transaction before returning success and reject an enclosing transaction that could otherwise delay the actual commit. Storage failures propagate to framework error handling.

## Developing and publishing packages

Run `composer install` at the repository root. The development autoloader includes both package namespaces and the development dependencies include both frameworks and Doctrine/Twig. Run `composer check` and `php tools/check-package-installs.php`; see [CONTRIBUTION.md](../CONTRIBUTION.md).

The root manifest's runtime dependencies and discovery remain unchanged. Framework-specific runtime dependencies and Laravel provider discovery are declared in each optional package's own manifest. The independent installation check uses temporary consumers with path repositories, which catches missing package autoload/dependency declarations without relying on the root development autoloader.

Composer cannot install a package directly from a GitHub repository subdirectory. Maintainers must publish each package from its own distribution repository before registering it on Packagist. To prepare reviewable split branches locally:

```sh
git subtree split --prefix=packages/laravel --branch=split/laravel
git subtree split --prefix=packages/symfony --branch=split/symfony
```

Push these branches to the respective package repositories only as part of an authorized publication. Each split contains its own manifest, source, templates, usage guide, and license. Keep the base `dev-main` dependency explicit until compatible stable tags exist; do not tag a split independently with an incompatible base constraint. Creating distribution repositories, Packagist registrations, release tags, and automatic split publication is a separate maintainer action.
