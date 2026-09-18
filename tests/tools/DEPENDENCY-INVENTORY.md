# Dependency inventory — PHP 8.3 compatibility branch

This inventory preserves the historical PHP 8.1/8.2 baselines and records the reviewed T-4B Composer closure for the PHP 8.3 compatibility branch.

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
composer.lock  913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033
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
- PHPUnit 9.5.8 is a test-only runtime artifact acquired from the official PHAR endpoint and verified against `phpunit-9.5.8.phar.sha256`; the binary is not tracked.
- PHPUnit 9.5.8 includes the PHAR compatibility fix needed for PHP 8.1.
- The PHAR is an acceptance tool and does not change application runtime dependencies.
- Official runner SHA-256: `11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517`.

## PHP 8.2 boundary

PHP 8.1 is the stable release target. PHP 8.2 work must happen on `compatibility/php82` after the PHP 8.1 tag is finalized.

The main remaining compatibility risks include dynamic-property deprecations in Smarty 2.6.31, Propel 1, Creole, generated model classes, and other legacy third-party code. U-4.1 must inventory and ratchet those deprecations while preserving every PHP 8.1 historical preflight, all 16 browser tests, and at least 604 PHPUnit tests.

Do not update the stable dependency baseline merely to suppress PHP 8.2 deprecations. Review dependency upgrades and source compatibility changes as separate, testable units.

## T-2B rev E accepted delta

| Package | Previous | Accepted | Scope | Reason |
|---|---:|---:|---|---|
| `nette/schema` | v1.2.2 | v1.2.5 | production | Removes the PHP `<8.2` platform ceiling while retaining PHP 8.1 support. |
| `phpspec/prophecy` | v1.15.0 | v1.16.0 | development lock only | Adds PHP 8.2 compatibility for the Composer development graph. |
| `nette/utils` | v3.2.8 | v3.2.8 | production | Intentionally unchanged; supports PHP 8.1 and 8.2. |

## T-3A test-only PHPUnit 11 lane

- `phpunit-11.phar`: PHPUnit 11.5.49, test-only, acquired separately from `https://phar.phpunit.de/phpunit-11.5.49.phar`.
- Pinned SHA-256: `b20ea78f38bc6abccc96ace605c471b1d11912ad6f0285c74415919050d234a6`.
- This does not alter `composer.json`, `composer.lock`, production vendor code, PHPUnit 9.5 baseline, or the accepted PHPUnit 10.5.64 lane.


## Acquisition-only PHPUnit PHAR policy (T-3B)

- `phpunit-9.5.8.phar`: official PHPUnit 9.5.8 test runtime; SHA-256 `11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517`.
- `phpunit-10.phar`: official PHPUnit 10.5.64 test runtime; SHA-256 `a823d916151f628dd9943ccc81a98bcfbba9c5babf53f27be6c7dccc89f8ee23`.
- `phpunit-11.phar`: official PHPUnit 11.5.49 test runtime; SHA-256 `b20ea78f38bc6abccc96ace605c471b1d11912ad6f0285c74415919050d234a6`.
- All three binaries are ignored and untracked. Acquisition scripts and checksum manifests remain tracked to provide reproducible, authenticated test tooling.


## R-2 PHP 8.2 production-release packaging

R-2 introduces no Composer dependency or production-code change. The release is built from committed `HEAD`; the accepted `composer.lock` SHA-256 remains `9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3`.

Release-only exclusions are `tests/`, all `phpunit*.xml` files, PHAR binaries, acceptance/discovery logs, and CI/VCS/editor metadata. Test acquisition scripts and PHPUnit runtimes remain repository/developer concerns and are not production dependencies. The archive contains a commit-bound manifest and has a separately generated SHA-256 file.

## S-1 installer/runtime boundary

S-1 changes no Composer package, lock entry, PHAR version, or production dependency. The accepted `composer.lock` SHA-256 remains `9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3`.

The installer runtime boundary is now PHP `>=7.4` and `<8.3`, matching the completed PHP 8.2 acceptance scope while keeping PHP 8.3 closed until its own discovery and remediation phase. cURL, SOAP, and LDAP remain runtime extension requirements detected from the active web SAPI; they are not Composer dependencies.

The Laragon repair helper changes no repository or Composer dependency. It synchronizes Apache's preloaded `nghttp2.dll` with the selected PHP 8.2 distribution after creating a timestamped backup, and updates only extension directives in the active `php.ini`. OpenSSL DLLs are intentionally not copied.



## T-4A PHP 8.3 / PHPUnit 12 acceptance lane

- Runtime target: PHP 8.3.x; initial validated environment is PHP 8.3.33 ZTS VS16 x64.
- `phpunit-12.phar`: PHPUnit 12.5.35, test-only and acquired from `https://phar.phpunit.de/phpunit-12.5.35.phar`.
- SHA-256: `2c076d3d30f3bca762b13d996ad665d23220bc29afdb98a40387f7896b324195`.
- The PHAR is ignored and untracked; only its acquisition script and checksum manifest are versioned.
- T-4A made no dependency change; T-4B below supersedes its discovery lock with the reviewed minimal closure.
- `run-t4a-checks.cmd` retains every historical preflight, Composer gate, all 16 browser tests, and PHPUnit 9/10/11 before the strict PHPUnit 12 discovery lane.
- The unit-test acceptance ratchet remains exactly 604 tests and 9241 assertions; the PHP harness parse ratchet is 99 files.


## T-4B PHP 8.3 Composer closure

Accepted lock SHA-256: `913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033`; content-hash remains `080123d899a9a1dacbeb10a5d1f5fe04`; cardinality remains 106 production / 41 development packages.

| Package | Previous | Accepted | Scope | PHP constraint | Source reference |
|---|---:|---:|---|---|---|
| `nette/utils` | v3.2.8 | v3.2.10 | production | `>=7.2 <8.4` | `a4175c62652f2300c8017fb7e640f9ccb11648d2` |
| `phpspec/prophecy` | v1.16.0 | v1.18.0 | development lock only | `^7.2 || 8.0.* || 8.1.* || 8.2.* || 8.3.*` | `d4f454f7e1193933f04e6500de3e79191648ed0c` |
| `nette/schema` | v1.2.5 | v1.2.5 | production | `7.1 - 8.3` | `0462f0166e823aad657c9224d0f849ecac1ba10a` |
| `phpunit/phpunit` | 9.5.0 | 9.5.0 | development lock only | `>=7.3` | `8e16c225d57c3d6808014df6b1dd7598d0a5bbbe` |

No package was added or removed. Tracked production `vendor/nette/utils` and generated `vendor/composer` metadata are expected to change after `composer install --no-dev`; development vendor trees must remain untracked and excluded from production archives.


## T-4C PHP 8.3 runtime baseline

No Composer or production dependency changed. Two DB-free unit-test runtime guards now accept `80100 <= PHP_VERSION_ID < 80400`, preserving PHP 8.1/8.2 coverage, adding PHP 8.3, and intentionally rejecting PHP 8.4+. The frozen legacy UTF-8 fixture, oracle, and expected conversions are byte-for-byte unchanged.


## T-4D PHP 8.3 installer boundary

- Installer minimum remains PHP 7.4; the exclusive unsupported ceiling moves from PHP 8.3 to PHP 8.4.
- PHP 8.3.33 is accepted by the installer; PHP 8.4+ remains explicitly rejected.
- The English PO source, fresh-install SQL label, and compiled English catalog recommend PHP 8.3.
- No Composer package, lock metadata, vendor file, frozen fixture, or unit-test baseline changes in T-4D.
- The historical S-1 PHP 8.2 installer verifier and R-2 PHP 8.2 release lock record remain unchanged.
