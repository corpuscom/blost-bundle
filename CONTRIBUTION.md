# Contributing

Thanks for contributing to `corpuscom/blost-bundle`. Start with the [usage guide](README.md) and [protocol v1](docs/protocol.md). Automated coding agents must also follow [AGENTS.md](AGENTS.md).

## Development setup

Fork or clone the repository, create a branch from `main`, and install dependencies:

```sh
git clone https://github.com/corpuscom/blost-bundle.git
cd blost-bundle
git switch -c fix/describe-the-change
composer install
```

Use your fork's clone URL if you do not have write access. PHP 8.2 is the package's minimum; the dependency versions Composer selects may require a newer PHP version. To reproduce a particular supported combination, use the constraints and PHP version in [the CI workflow](.github/workflows/tests.yml).

| PHP in CI | Symfony | Laravel |
| --- | --- | --- |
| 8.2 | 6.4 | Not installed |
| 8.3 | 7 | 12 |
| 8.4 | 8 | 13 |
| 8.5 | 8 | 13 |

Symfony 6.4 and Laravel 12 require different major versions of HttpKernel and cannot be tested in the same dependency environment. Use separate checkouts or temporary directories when changing dependency constraints to reproduce a CI job. Keep those temporary constraints out of your patch.

## Making changes

Keep changes focused and follow the existing PHP style: four-space indentation, existing namespaces, explicit types where practical, and strict types in source classes. Source syntax must remain compatible with PHP 8.2.

Keep publishing behavior in the shared API. Framework adapters register services, configuration, and routes; they should not implement separate versions of authentication or validation. The host CMS owns persistence, public pages, SEO output, and media imports.

For a behavior change, add a regression test that checks the observable result. Use shared API tests for validation and publishing semantics, and the relevant framework integration test for registration or request-dispatch changes. Preserve the existing behavior of the other adapter.

When changing a payload or response, update the [protocol](docs/protocol.md), affected examples, and usage instructions together. Coordinate incompatible changes with the Blost sender before introducing them. Do not change protocol v1 silently.

For bug reports, include the package/framework/PHP versions, reproduction steps, expected and actual behavior, and relevant sanitized errors. Replace tokens and private article content with sample data.

## Validation

Run the complete check before submitting code changes:

```sh
composer check
```

This runs strict Composer validation and PHPUnit. For focused iteration:

```sh
composer test -- --filter BlostApiTest
composer test -- --filter SymfonyIntegrationTest
composer test -- --filter LaravelIntegrationTest
```

The Laravel test is skipped when Laravel is absent in the Symfony-only environment. A passing local suite covers the versions installed locally; the remaining supported combinations are checked by CI.

For documentation-only changes, check links, configuration names, examples, and Markdown formatting. A full test rerun is unnecessary unless the edit exposes a behavior concern. Check whitespace for every patch:

```sh
git diff --check
```

Do not commit installed or generated files: `vendor/`, `composer.lock`, `.phpunit.cache/`, or `config/reference.php`.

## Commits and pull requests

Use a concise emoji-prefixed imperative commit subject, such as `🐛 Preserve publication dates on retries`. Add a body when it helps explain the reason for a change.

Credit every contributing AI agent using its actual identity. Choose the trailer according to whether the agent has an available, valid email address:

- **Valid email available:** use `Co-authored-by: Agent (Model/version) <email>`. This applies to Codex, Claude, and any other agent with a valid email address.
- **No valid email available:** use `Assisted-by: Agent (Model/version)` without an email address.

For Codex (GPT-6) contributions, use the confirmed identity:

```text
Co-authored-by: Codex (GPT-6) <codex@openai.com>
```

For Claude contributions, use the confirmed email address and include the actual model/version when known:

```text
Co-authored-by: Claude (Model/version) <noreply@anthropic.com>
```

Replace `Model/version` with the contributing Claude model/version. If it is unavailable, omit the parenthetical:

```text
Co-authored-by: Claude <noreply@anthropic.com>
```

For an agent without a valid email address, use this template with its actual name and known model/version:

```text
Assisted-by: Agent (Model/version)
```

Omit the model/version parenthetical when it is unavailable. Never invent an email address or use another agent's identity. If several agents contributed, include a separate trailer for each using the appropriate format.

Separate trailers from the body with a blank line. Preserve existing human trailers when amending a commit, and verify the final message after local hooks run.

Open a pull request against `main`. Explain the problem, resulting behavior, and checks actually run. Include any compatibility or deployment implications. Do not include unrelated refactoring or generated files. Changes to one framework must retain compatibility with the other.

Maintainers manage merges, release tags, and publication. Adding a dependency or changing supported versions should include the reason and corresponding CI/documentation changes.
