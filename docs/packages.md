# Ready-to-use framework integrations

The repository contains three Composer packages:

| Package | Purpose | Location |
| --- | --- | --- |
| [corpuscom/blost-bundle](https://packagist.org/packages/corpuscom/blost-bundle) | Shared publishing API and lightweight framework adapters for an existing CMS. | Repository root |
| [corpuscom/blost-laravel](https://packagist.org/packages/corpuscom/blost-laravel) | Eloquent storage, publishable migration, publisher, and Blade blog pages. | [Laravel](../packages/laravel/README.md) |
| [corpuscom/blost-symfony](https://packagist.org/packages/corpuscom/blost-symfony) | Doctrine entity and mapping, publisher, migration generation, and Twig blog pages. | [Symfony](../packages/symfony/README.md) |

All three packages are published on Packagist. Stable 1.x releases are available for all three. Install your chosen framework integration with a `^1.0` constraint; Composer installs its compatible stable shared bundle automatically, as shown in the [Laravel guide](../packages/laravel/README.md#install) or [Symfony guide](../packages/symfony/README.md#install). No repository clone, custom Composer repository, or project-wide `minimum-stability` change is needed.

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

Composer cannot install a package directly from a GitHub repository subdirectory. The distribution repositories are [corpuscom/blost-laravel](https://github.com/corpuscom/blost-laravel) and [corpuscom/blost-symfony](https://github.com/corpuscom/blost-symfony); each has its Composer manifest at the root and is registered on Packagist.

### Automatic synchronization

Make all package changes in this monorepo. The [Sync packages workflow](../.github/workflows/sync-packages.yml) splits both package directories and pushes them to the distribution repositories' `main` branches whenever `packages/**` or the workflow changes on `main`. Other branches and pull requests do not publish packages. The workflow can also be run manually from the Actions tab on `main`, including after credential rotation or to retry a failed sync.

Each run checks out the latest `main` with complete history. Runs are serialized and pushes are ordinary fast-forward pushes; divergent distribution history fails visibly rather than being overwritten. Do not commit directly to the distribution repositories. Commit authors and trailers are preserved by the subtree split. Tags and releases are not copied or created.

Each destination repository has its own write-enabled SSH deploy key. The matching private key is stored only as an Actions secret in `corpuscom/blost-bundle`:

| Destination | Source repository secret |
| --- | --- |
| [corpuscom/blost-laravel](https://github.com/corpuscom/blost-laravel) | `BLOST_LARAVEL_DEPLOY_KEY` |
| [corpuscom/blost-symfony](https://github.com/corpuscom/blost-symfony) | `BLOST_SYMFONY_DEPLOY_KEY` |

To rotate a key, generate a new Ed25519 key pair, add its public key as a write-enabled deploy key on the matching destination, replace the source repository's matching secret with the private key, run the workflow manually, then remove the old destination deploy key. Never commit private keys. The workflow uses a pinned GitHub SSH host key; if GitHub rotates it, verify the replacement through GitHub's official documentation/API before updating the workflow.

Shared bundle changes do not need copying into the distribution repositories: both packages depend on `corpuscom/blost-bundle` through Composer. Packagist webhook/update configuration remains separate from repository synchronization.

For a local inspection without publishing:

```sh
git subtree split --prefix=packages/laravel --branch=split/laravel
git subtree split --prefix=packages/symfony --branch=split/symfony
```

Each split contains its own manifest, source, templates, usage guide, and license. The framework packages require `corpuscom/blost-bundle:^1.0`. Release tags remain maintainer actions; repository synchronization does not create stable releases.

## Stable releases

All three repositories use matching `v1.0.0` tags for the initial stable release. Install with `^1.0` to receive compatible 1.x updates. The shared API supports protocol v1; framework-specific setup remains in each package guide.

Before releasing, update installation documentation and dependency constraints in this monorepo, run the validation commands, and push the changes to `main`. Wait for CI and package synchronization to succeed, then verify that both distribution repository trees match their package directories. Tag and publish the shared bundle first, followed by the matching commits in each distribution repository. Never tag the monorepo commit as a framework package release. Verify that Packagist indexes each tag and that stable consumer installations resolve without relaxing stability.

Synchronization only updates branch history; maintainers create tags and GitHub releases separately in each repository.
