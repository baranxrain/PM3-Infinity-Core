# Dependency inventory — PHP 8.1 stable release

This inventory describes the accepted Composer baseline for **PM3-Infinity-Core 3.8.3-PHP81.1**. It was generated from the reviewed `composer.json` and `composer.lock` used by the cumulative U-3.19 acceptance suite.

- Repository: <https://github.com/baranxrain/PM3-Infinity-Core>
- Stable branch: `release/php81-stable`
- Release tag: `pm3infinity-3.8.3-php81.1`
- Runtime target: PHP 8.1.x
- License: `AGPL-3.0-only`

## Composer baseline

| Item | Accepted value |
| --- | --- |
| `composer.lock` content-hash | `080123d899a9a1dacbeb10a5d1f5fe04` |
| Locked packages (production / development) | 106 / 41 |
| PHP constraint in `composer.json` | `>=7.4` |
| PHPUnit constraint in `require-dev` | `9.5` |
| Offline acceptance runner | PHPUnit 9.5.8 PHAR |

Accepted file SHA-256 values:

```text
composer.json  708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2
composer.lock  c6d4c0da3da7483ad9499f8fdc5a137997cf57a55b1bbeee09f8210711a4c50f
```

Any intentional Composer change must update the dependency fixture and all historical dependency-baseline guards together. A hash must never be changed only to make a test pass; review the lock diff first and rerun the complete U-3.19 suite.

## Updated Google client chain

The PHP 8.1 release intentionally updates the Google API client and its security/runtime dependency chain.

| Package | Locked version | Locked source reference |
| --- | --- | --- |
| `google/apiclient` | v2.19.0 | `b18fa8aed7b2b2dd4bcce74e2c7d267e16007ea9` |
| `google/auth` | v1.44.0 | `5670e56307d7a2eac931f677c0e59a4f8abb2e43` |
| `google/apiclient-services` | v0.459.0 | `3b45be50c8420dea56b6f2326ff745175c3cafb8` |
| `guzzlehttp/guzzle` | 7.9.3 | `7b2f29fe81dc4da0ca0ea7d42107a0845946ea77` |
| `guzzlehttp/psr7` | 2.13.1 | `95e7828100de18b4e269fb1703be530082d5166d` |
| `firebase/php-jwt` | v6.11.1 | `d1e91ecf8c598d073d0995afa8cd5c75c6e19e66` |
| `monolog/monolog` | 2.11.1 | `bf2403f591c5a431b6e0e1170cbadd3d64499564` |
| `phpseclib/phpseclib` | 3.0.57 | `d17e0ddaeaf6f22f7e007cbb437d78792fe2a0e4` |

The update replaces the previous `google/apiclient` v2.9.0 and `monolog/monolog` 2.8.0 baseline. Production application source was not changed as part of this dependency refresh.

## Retained private Colosa packages

| Package | Locked version | Locked source reference | Status |
| --- | --- | --- | --- |
| `colosa/MichelangeloFE` | dev-release/3.8.0 | `01abeb68fdec5844191911a2f48937b51e768164` | Retained |
| `colosa/pmUI` | dev-release/3.8.0 | `bd462bc978530dfdc05289f624c36194107660f3` | Retained |

## Removed build-time Colosa requirements

| Package | Previous version | Current Composer status |
| --- | --- | --- |
| `colosa/pmDynaform` | dev-release/3.8.0 | Removed from `composer.json` and `composer.lock` |
| `colosa/taskscheduler` | dev-release/1.0.3 | Removed from `composer.json` and `composer.lock` |

Their built public assets are already committed under `workflow/public_html/lib/`, so the accepted application distribution does not need these packages during a normal Composer install. Rebuilding their frontend assets from source would require restoring their repository and requirement entries and using an authorized environment with access to the private repositories.

The offline acceptance suite never requires Bitbucket or other network access.

## Watched framework and runtime pins

| Package | Locked version | Locked source reference |
| --- | --- | --- |
| `laravel/framework` | v8.83.24 | `a684da6197ae77eee090637ae4411b2f321adfc7` |
| `luracast/restler` | 3.0.0 | `e82d5622f5a1798c3c208867184a469fb4fd445c` |
| `smarty/smarty` | v2.6.31 | `4ab9757b492f08a38f68123a6e7c1df7110bbc49` |
| `predis/predis` | v1.1.1 | `f0210e38881631afeafb56ab43405a92cafd9fd1` |
| `nikic/php-parser` | v4.14.0 | `34bea19b6e03d8153165d8f30bba4c3be86184c1` |
| `tecnickcom/tcpdf` | 6.4.4 | `42cd0f9786af7e5db4fcedaa66f717b0d0032320` |
| `aws/aws-sdk-php` | 3.236.0 | `bff1f1ade00c758ea27f498baee1fa16901e5bfd` |
| `phpmailer/phpmailer` | v6.6.4 | `a94fdebaea6bd17f51be0c2373ab80d3d681269b` |

These packages were not part of the Google client refresh. They remain watched because changes can affect the legacy engine, generated models, mail, PDF, queue, parser, or API behavior.

## Installation modes

Production installation:

```bat
composer install --no-dev
```

Development installation, when repository access and all build requirements are available:

```bat
composer install
```

The acceptance suite does not require development packages under `vendor/`; it uses the pinned standalone PHPUnit PHAR in `tests/tools/`. A PHPCS post-install warning under `--no-dev` is currently non-blocking, but Composer resolution, download, extraction, autoload, and script failures remain blocking.

## Accepted offline validation

The dependency-updated PHP 8.1 baseline passed the full cumulative runner:

```text
Browser tests: 16, Passed: 16, Failures: 0
U319_BROWSER_ACCEPTANCE=PASS
OK (604 tests, 9241 assertions)
U319_ACCEPTANCE=PASS
```

Accepted U-3.19 log SHA-256:

```text
6bba037c733d665a094669bfe67e9b0ccedcdb8eee5d1762638d2785b5f5dab3
```

The authoritative release command is:

```bat
tests\tools\run-u319-checks.cmd
```

## PHPUnit acceptance-runtime exception

- The Composer development constraint remains `phpunit/phpunit=9.5` and currently locks 9.5.0.
- The suite bundles the official `phpunit-9.5.8.phar` as its standalone offline PHP 8.1 runner.
- PHPUnit 9.5.8 includes the PHAR compatibility fix needed for PHP 8.1.
- The PHAR is an acceptance tool and does not change application runtime dependencies.
- Official runner SHA-256: `11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517`.

## PHP 8.2 boundary

PHP 8.1 is the stable release target. PHP 8.2 work must happen on `compatibility/php82` after the PHP 8.1 tag is finalized.

The main remaining compatibility risks include dynamic-property deprecations in Smarty 2.6.31, Propel 1, Creole, generated model classes, and other legacy third-party code. U-4.1 must inventory and ratchet those deprecations while preserving every PHP 8.1 historical preflight, all 16 browser tests, and at least 604 PHPUnit tests.

Do not update the stable dependency baseline merely to suppress PHP 8.2 deprecations. Review dependency upgrades and source compatibility changes as separate, testable units.
