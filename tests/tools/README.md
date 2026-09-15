# PM3-Infinity-Core Offline Compatibility Test Guide

This directory contains the DB-free, offline compatibility and regression suite for **PM3-Infinity-Core**, a maintained fork of ProcessMaker 3.8.3 Community.

- Repository: <https://github.com/baranxrain/PM3-Infinity-Core>
- Stable PHP 8.1 branch: `release/php81-stable`
- Stable release tag: `pm3infinity-3.8.3-php81.1`
- License: `AGPL-3.0-only`
- Target runtime for this release: PHP 8.1.x on Windows/Laragon

## Current accepted baseline

The final PHP 8.1 acceptance run completed on September 15, 2026 using PHP 8.1.10 on Windows:

```text
Browser tests: 16, Passed: 16, Failures: 0
U319_BROWSER_ACCEPTANCE=PASS
OK (604 tests, 9241 assertions)
U319_ACCEPTANCE=PASS
```

Acceptance-log SHA-256:

```text
6bba037c733d665a094669bfe67e9b0ccedcdb8eee5d1762638d2785b5f5dab3
```

Assertion totals may vary slightly with environment details. The test count and PASS markers must match.

## Run the complete suite

Open a new Laragon Terminal after selecting PHP 8.1, change to the repository root, and run:

```bat
tests\tools\run-u319-checks.cmd
```

This is the authoritative cumulative runner. It executes all historical preflights in order, the browser runtime suite, Composer validation, PHAR integrity checks, and the complete DB-free PHPUnit unit suite. Normal users and release maintainers do **not** need to run every historical runner separately.

The output is written to:

```text
U319-ACCEPTANCE.log
```

A successful run must contain:

```text
Browser tests: 16, Passed: 16, Failures: 0
U319_BROWSER_ACCEPTANCE=PASS
OK (604 tests, ... assertions)
U319_ACCEPTANCE=PASS
```

Every `[EXIT]` line must be `0`. The final marker alone is not enough if the log was truncated or edited.

## Requirements

- Windows with Laragon, or an equivalent PHP 8.1 CLI environment
- PHP 8.1.x selected in the same terminal used to run the suite
- Required PHP extensions reported by the preflights, including JSON, mbstring, PCRE, tokenizer, and PHAR
- Microsoft Edge, Google Chrome, or Chromium for the U-3.19 browser checks
- No database, workspace, web server, Packagist access, Bitbucket access, or network access is required
- The official PHPUnit 9.5.8 PHAR stored at `tests/tools/phpunit-9.5.8.phar`

## Browser runtime checks

`run-u319-browser-checks.cmd` discovers Edge, Chrome, or Chromium and runs `tests/browser/u319-client-runtime.html` in headless mode. It tries the modern headless mode first and falls back to the legacy mode.

The 16 browser cases cover:

- valid array and nested JSON responses;
- malformed JSON fail-closed behavior;
- simple and dotted callback resolution;
- callback receiver (`this`) and argument preservation;
- rejection of unknown and empty callbacks;
- function and named PagedTable hooks;
- rejection of unknown hooks;
- DOM script execution, synchronous ordering, and node cleanup;
- generated field-condition true and false behavior.

The browser stage must report:

```text
Browser tests: 16, Passed: 16, Failures: 0
U319_BROWSER_ACCEPTANCE=PASS
```

## Test architecture

The suite intentionally avoids application boot, Laravel/Artisan startup, database access, workspace configuration, and external services. It uses:

- static source scanners and compatibility ledgers;
- immutable fixtures and decreasing budgets;
- parser and syntax guards;
- focused behavioral unit tests;
- historical stage verifiers;
- an offline PHPUnit PHAR;
- a real browser-side JavaScript harness.

The suite currently contains 88 PHP harness files and 604 PHPUnit tests. The cumulative U-3.19 runner has 50 stages.

## Complete runner index

Historical runners are retained for forensic reproduction and focused debugging. Use `run-u319-checks.cmd` for release acceptance.

| Unit | Runner | Purpose |
| --- | --- | --- |
| U-1 | `run-u1-checks.cmd` | Base offline harness, engine API anchors, trigger API locks, dependency fixture, and PHPUnit PHAR integrity |
| U-2.1 | `run-u2-checks.cmd` | PHP 8 executable compatibility ledger and initial removed/deprecated construct fixes |
| U-2.2.1 | `run-u22-checks.cmd` | Legacy UTF-8 behavioral contract and helper oracle |
| U-2.2.2 | `run-u222-checks.cmd` | UTF-8 encode call-site migration |
| U-2.2.3 | `run-u223-checks.cmd` | UTF-8 decode call-site migration |
| U-2.3.1 | `run-u231-checks.cmd` | `strftime` inventory and live-tree drift protection |
| U-2.3.2a | `run-u232a-checks.cmd` | `LegacyStrftime` helper contract and accepted oracle |
| U-2.3.2 | `run-u232-checks.cmd` | Initial strftime compatibility migration |
| U-2.3.3 | `run-u233-checks.cmd` | Additional strftime call-site migration and ratchet checks |
| U-2.4.1 | `run-u241-checks.cmd` | First high-risk deprecated API migration guard |
| U-2.4.2 | `run-u242-checks.cmd` | Follow-up high-risk migration and closure checks |
| U-2.5 | `run-u25-checks.cmd` | Incremental PHP 8 compatibility hardening |
| U-2.6 | `run-u26-checks.cmd` | Incremental PHP 8 compatibility hardening |
| U-2.7 | `run-u27-checks.cmd` | Incremental PHP 8 compatibility hardening |
| U-2.8 | `run-u28-checks.cmd` | Executable/textual inventory and regression ratchets |
| U-2.9 | `run-u29-checks.cmd` | Eval inventory classification and historical closure guard |
| U-3.0 | `run-u30-checks.cmd` | PMScript trigger execution migration baseline |
| U-3.1 | `run-u31-checks.cmd` | Incremental eval-removal migration and regression checks |
| U-3.2 | `run-u32-checks.cmd` | Incremental eval-removal migration and regression checks |
| U-3.3 | `run-u33-checks.cmd` | Incremental eval-removal migration and regression checks |
| U-3.4 | `run-u34-checks.cmd` | Incremental eval-removal migration and regression checks |
| U-3.5 | `run-u35-checks.cmd` | Incremental eval-removal migration and regression checks |
| U-3.6 | `run-u36-checks.cmd` | Incremental eval-removal migration and regression checks |
| U-3.7 | `run-u37-checks.cmd` | PHP 8 compatibility closure and cumulative ratchet checks |
| U-3.8 | `run-u38-checks.cmd` | Compatibility hardening and historical harness expansion |
| U-3.9 | `run-u39-checks.cmd` | Compatibility hardening and historical harness expansion |
| U-3.10 | `run-u310-checks.cmd` | Compatibility hardening and cumulative preflight expansion |
| U-3.11 | `run-u311-checks.cmd` | XMLForm eval-site migration |
| U-3.12 | `run-u312-checks.cmd` | Bootstrap, G, and WebResource eval-site migration |
| U-3.13 | `run-u313-checks.cmd` | Final Gulliver eval migration and safe expression evaluator |
| U-3.14 | `run-u314-checks.cmd` | Dynamic model-dispatch eval migration |
| U-3.15 | `run-u315-checks.cmd` | AdditionalTables eval migration and ratchet reduction |
| U-3.16 | `run-u316-checks.cmd` | XMLConnection, Event, and Process expression parser migration |
| U-3.17 | `run-u317-checks.cmd` | PMScript temporary execution bridge and executable-eval closure |
| U-3.18 | `run-u318-checks.cmd` | Client-side and textual eval-token closure |
| U-3.19 browser | `run-u319-browser-checks.cmd` | Real headless-browser runtime contracts |
| U-3.19 cumulative | `run-u319-checks.cmd` | Authoritative full PHP 8.1 release acceptance |
| U-6 historical | `run-u6-checks.cmd` | Retained dependency and compatibility regression gate |

## PHP 8.1 compatibility outcome

The accepted baseline has zero executable occurrences for the tracked deprecated/removed API families, including:

- `create_function`;
- legacy `each` usage;
- `ereg`/`eregi` family;
- executable `eval`;
- legacy MySQL extension calls;
- `strftime`/`strptime` compatibility targets;
- native `utf8_encode`/`utf8_decode` migration targets;
- removed interpolation and error-message constructs tracked by the PHP 8 ledger.

Textual inventories may retain known non-executable documentation, fixture, vendor, or false-positive entries. The executable-code budgets and historical fixtures are the release gates.

## Dependency baseline

The PHP 8.1 release intentionally updates the Google client dependency chain and related transitive packages:

| Package | Accepted version |
| --- | --- |
| `google/apiclient` | 2.19.0 |
| `google/auth` | 1.44.0 |
| `google/apiclient-services` | 0.459.0 |
| `guzzlehttp/guzzle` | 7.9.3 |
| `guzzlehttp/psr7` | 2.13.1 |
| `firebase/php-jwt` | 6.11.1 |
| `monolog/monolog` | 2.11.1 |
| `phpseclib/phpseclib` | 3.0.57 |

Current dependency-file SHA-256 values:

```text
composer.json  708119e1eb1f15b263a35366ff18116dcd828329f2481aa588efc49d81a33ad2
composer.lock  c6d4c0da3da7483ad9499f8fdc5a137997cf57a55b1bbeee09f8210711a4c50f
```

The dependency fixture and historical verifier hashes must be updated together after any intentional Composer change. Do not silence a mismatch without reviewing the lock diff and rerunning the full suite.

`colosa/pmdynaform` and `colosa/taskscheduler` are no longer Composer build-time requirements because their built public assets are committed under `workflow/public_html/lib/`. `colosa/pmui` and `colosa/michelangelofe` remain unchanged. Bitbucket access is not needed for the offline acceptance suite, but rebuilding those private Colosa packages would require restoring their repository/require entries and network access.

The normalized accepted strftime oracle SHA-256 is:

```text
a1c1102d8b1780066e31a2b9f4ce21c5d683a46ffe27e2f12a95f124a3f04518
```

## Composer installation note

Production dependencies may be installed with:

```bat
composer install --no-dev
```

Development packages, including PHPUnit, do not need to be installed into `vendor/` for acceptance because the suite uses its pinned PHAR. A PHPCS post-install warning under `--no-dev` is currently non-blocking, but all actual Composer install failures must be investigated.

## Git and line endings

The repository uses LF by default. Windows batch files must remain CRLF:

```gitattributes
* text=auto eol=lf
*.cmd text eol=crlf
*.bat text eol=crlf
*.phar binary
```

Do not normalize `.cmd` or `.bat` files to LF. The historical owner runners depend on Windows-compatible line endings.

## Failure triage

1. Find the first non-zero `[EXIT]` line.
2. Read the `[RUN]` and `[CMD]` lines immediately above it.
3. Fix the first `[FAIL]`; later stages were not executed.
4. Do not update a fixture or hash merely to make a test green. Confirm that the underlying change is intentional and reviewable.
5. Rerun `run-u319-checks.cmd` from the beginning.
6. Preserve and return the generated acceptance log unchanged.

Common cases:

- A PHP-version failure means the terminal is using the wrong PHP executable. Run `where php` and `php -v`.
- A PHAR hash failure means the bundled test runner changed or was corrupted.
- A browser discovery failure means Edge, Chrome, or Chromium is unavailable from PATH and common installation locations.
- A dependency hash failure requires synchronizing the reviewed Composer baseline across its fixture and historical guards.
- An inventory drift failure means the live tree no longer matches the accepted source inventory.

## Manual smoke tests before release

The offline suite does not replace application-level smoke testing. Before publishing the GitHub release, verify at least:

1. login and logout;
2. opening a process;
3. Dynaform Designer load and save;
4. starting and routing a simple case;
5. Task Scheduler behavior;
6. one Google API integration path, when credentials and network access are available;
7. one email or external-service path used by the deployment.

The `shared/` directory and database configuration are not tracked and must be configured separately for a runnable Laragon installation.

## Release checklist

1. Confirm a clean working tree with `git status`.
2. Run `tests\tools\run-u319-checks.cmd` on PHP 8.1.
3. Confirm all 16 browser tests and all 604 PHPUnit tests pass.
4. Complete the manual smoke tests.
5. Commit this README and any reviewed baseline synchronization changes.
6. Run the full suite again after the final documentation commit.
7. Create `pm3infinity-3.8.3-php81.1` on the exact accepted commit.
8. Build the release archive with `git archive`.
9. Publish the archive, SHA-256 manifest, handoff, and unmodified acceptance log in the GitHub Release.

## Starting PHP 8.2 work

PHP 8.2 compatibility work must be isolated from the stable PHP 8.1 branch:

```bat
git checkout -b compatibility/php82 pm3infinity-3.8.3-php81.1
git push -u origin compatibility/php82
```

The first PHP 8.2 unit is U-4.1. It will inventory and ratchet dynamic-property deprecations, update the runtime-version gate in a dedicated runner, and preserve the complete PHP 8.1 acceptance baseline.

Do not weaken or overwrite the stable PHP 8.1 gates while developing PHP 8.2 compatibility.
