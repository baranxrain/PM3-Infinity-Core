# Dependency inventory (U-1 baseline)

Generated from the shipped `composer.json` / `composer.lock`. No dependency was changed.

| Item | Value |
| --- | --- |
| composer.lock content-hash | `e02836395d01dfeff9d1ca2fff52c81d` |
| Locked packages (prod / dev) | 106 / 41 |
| PHP constraint in composer.json | `>=7.4` |
| PHPUnit (dev) | `9.5.0` |

## Private packages (build blockers outside an authorised machine)

| Package | Version | Locked reference |
| --- | --- | --- |
| `colosa/MichelangeloFE` | dev-release/3.8.0 | `01abeb68fdec5844191911a2f48937b51e768164` |
| `colosa/pmDynaform` | dev-release/3.8.0 | `0f68aac5747a4d7f7b20c06ca2eccd9870442094` |
| `colosa/pmUI` | dev-release/3.8.0 | `bd462bc978530dfdc05289f624c36194107660f3` |
| `colosa/taskscheduler` | dev-release/1.0.3 | `d757b2baf1c00c93009ce8c9da0bf1ade920e698` |

## Watched pins

| Package | Version |
| --- | --- |
| `laravel/framework` | v8.83.24 |
| `luracast/restler` | 3.0.0 |
| `smarty/smarty` | v2.6.31 |
| `monolog/monolog` | 2.8.0 |
| `predis/predis` | v1.1.1 |
| `nikic/php-parser` | v4.14.0 |
| `tecnickcom/tcpdf` | 6.4.4 |
| `google/apiclient` | v2.9.0 |
| `aws/aws-sdk-php` | 3.236.0 |
| `phpmailer/phpmailer` | v6.6.4 |

## Runtime target decision

- Target runtime: **PHP 8.1** (matches the maintained 3.8.x line and the owner's Laragon).
- `smarty/smarty 2.6.31`, Propel 1 and Creole in `thirdparty/` are the blockers for PHP 8.2+,
  mainly because PHP 8.2 deprecates dynamic properties, which these generated model classes rely on.
- New code is written to be 8.2-clean so the jump can happen later without another rewrite.

## R4 acceptance-runtime exception

- The unchanged project lock and its fixture intentionally keep `phpunit/phpunit=9.5.0` as the observed baseline.
- R4 bundles official `phpunit-9.5.8.phar` only as the standalone offline acceptance runner for PHP 8.1.
- PHPUnit 9.5.8 is the first 9.5.x PHAR release whose official changelog fixes issue #4740 (`phpunit.phar` does not work with PHP 8.1).
- This exception does not modify `composer.json`, `composer.lock`, installed dependencies, production engine code, or application runtime behavior.
- Official runner SHA-256: `11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517`.
