# Agent guidance

## Project

`corpuscom/blost-bundle` is one Composer package that receives articles from Blost in Symfony and Laravel applications. Keep the publishing API shared; framework adapters should only register routes, configuration, and services.

The host application implements persistence through `ArticlePublisher`. This package does not own a database schema, public blog pages, media storage, or SEO rendering. Examples demonstrate integrations with an application's existing CMS.

## Read first

- [README.md](README.md): installation and framework usage.
- [CONTRIBUTION.md](CONTRIBUTION.md): development setup, validation, and pull-request conventions.
- `docs/protocol.md`: the native API contract.
- `composer.json`: runtime requirements, optional framework dependencies, and scripts.
- `.github/workflows/tests.yml`: the supported dependency test matrix.

Inspect applicable instructions, the current branch, and the working-tree diff before editing. Preserve unrelated work.

## Repository layout

| Location | Responsibility |
| --- | --- |
| `src/Article.php`, `src/PublishedArticle.php` | Shared input and result objects. |
| `src/Contract/ArticlePublisher.php` | Application-supplied publishing contract. |
| `src/Http/BlostApi.php` | Authentication, validation, and API responses for both frameworks. |
| `src/Symfony/` | Bundle and dependency-injection configuration. |
| `config/routes.php` | Symfony route registration. |
| `src/Laravel/` | Provider, controller, and Laravel routes. |
| `config/blost.php` | Publishable Laravel configuration. |
| `tests/` | Shared API tests and framework integration tests. |
| `examples/` | Eloquent and Doctrine adapters to adapt to a host CMS. |

## Implementation rules

- Maintain PHP 8.2-compatible source syntax and the constraints declared in Composer. Each framework version may require a newer PHP version.
- Follow the existing namespaces, four-space indentation, and PHP style. Use strict types in source classes and explicit types where practical.
- Keep Laravel and Symfony FrameworkBundle optional runtime integrations. Do not introduce a mandatory dependency on either complete framework, Doctrine, or Eloquent for the shared API.
- Keep DTOs immutable and the publisher interface independent of framework-specific request or model classes.
- Read Laravel environment values through configuration so `config:cache` works. Preserve package discovery, config publishing, and serializable controller routes for `route:cache`.
- Preserve Symfony bundle resource paths, service registration, and configurable publisher binding.
- Do not add a formatter, static analyzer, or other dependency solely for a small change when existing tooling is sufficient.

## Protocol and persistence invariants

- Authenticate every endpoint. An unconfigured or empty token must deny requests. Compare secrets with `hash_equals`; never log or return tokens.
- Preserve protocol v1's routes: `GET /blost/v1/status` and `PUT /blost/v1/articles/{uuid}`. Successful publication returns the normalized UUID and an absolute HTTP(S) article URL.
- Validate before invoking the publisher. Keep bounded body reads and the documented JSON, UUID, timestamp, URL, and size checks. Do not trust `Content-Length` as the sole size limit.
- Use the stable Blost UUID for repeated updates. Publisher examples and documentation must require a unique database index and handle concurrent creation without duplicate articles.
- Acknowledge success only after durable persistence. Let publisher failures reach the host framework's error handling; do not convert a storage failure into a successful response.
- Preserve editorial HTML. Do not flatten article content with `strip_tags` or add unconditional escaping to stored HTML. Rendering and the editorial content policy belong to the host CMS.
- Optional null metadata clears stored values. A null publication timestamp preserves an existing first publication date or uses the current time for a new article.
- Pass image URLs to the publisher without downloading remote resources in the shared API. Media import belongs to the host application's pipeline.
- Keep `docs/protocol.md`, the README, examples, and tests aligned with behavior. Coordinate wire-contract changes with Blost's sender; changes to another repository require task authorization.

## Validation

Follow the [contribution guide's validation commands](CONTRIBUTION.md#validation) and the dependency matrix in `.github/workflows/tests.yml`. Run `composer check` for code changes and `git diff --check` for every patch. Documentation-only changes require link, example, and formatting checks; do not rerun the entire suite without a behavior concern.

Add focused regression tests for behavior changes. Cover the shared API and the affected framework adapter; use the existing integration tests to verify real registration and request dispatch. Authentication failures and invalid payloads must never invoke persistence.

Use isolated environments for incompatible framework dependency combinations. Do not change the package's supported constraints just to accommodate a local setup, or commit temporary matrix constraints and generated files.

## Documentation and releases

- Document installation from Packagist without requiring a custom repository or globally relaxing Composer stability.
- Check published versions and Git tags before choosing the install constraint. When only the main development version is available, document `corpuscom/blost-bundle:dev-main` explicitly.
- Make clear that applications must implement the publisher, supply database fields and public routes, and configure the same token in Blost.
- Keep examples compatible with their stated framework and explain application-specific model, entity, and route assumptions.
- Do not create release tags, publish releases, change repository settings, or merge PRs unless requested.

## Git and attribution

- Follow the user's requested branch and publication scope. `main` is the repository's default branch. Push only when authorized; never force-push shared history without authorization.
- Follow the [commit and PR conventions](CONTRIBUTION.md#commits-and-pull-requests). Inspect the actual diff before committing and verify the message after hooks run.
- Use the specified `Co-authored-by` trailer for Codex (GPT-6) contributions; do not substitute `Assisted-by` or copy that identity for another agent. Preserve existing human trailers.
- Report what changed, the checks actually run, and any unresolved validation limits. Never describe a configured CI matrix as having passed locally unless it was executed.
