# PM3-Infinity-Core Offline Compatibility Test Guide

This directory contains the DB-free, offline compatibility and regression suite for **PM3-Infinity-Core**, a maintained fork of ProcessMaker 3.8.3 Community.

- Repository: <https://github.com/baranxrain/PM3-Infinity-Core>
- Stable PHP 8.2 branch: `release/php82-stable`
- Stable PHP 8.2 release tag: `pm3infinity-3.8.3-php82.1`
- License: `AGPL-3.0-only`
- Active discovery target: PHP 8.3.x on Windows/Laragon with PHPUnit 12

## Current accepted baseline

The stable PHP 8.2 release gate completed on September 17, 2026 using PHP 8.2.33 on Windows. The active T-4A discovery environment is PHP 8.3.33 with PHPUnit 12.5.35.

```text
Browser tests: 16, Passed: 16, Failures: 0
PHPUnit 9.5.8:   OK (604 tests, 9241 assertions)
PHPUnit 10.5.64: OK (604 tests, 9241 assertions)
PHPUnit 11.5.49: OK (604 tests, 9241 assertions)
R2_BUILD=PASS
R2_PREFLIGHT=PASS
R2_ACCEPTANCE=PASS
```

T-4A adds PHPUnit 12 as a strict discovery lane. T-4B accepts the minimal PHP 8.3 dependency closure while preserving production release exclusions and the Composer PHPUnit 9.5 baseline.

## Run the complete suite

Open a new Laragon Terminal after selecting PHP 8.3, change to the repository root, and run:

```bat
tests\tools\run-t4c-checks.cmd
```

This is the authoritative cumulative runner. It executes all historical preflights in order, the browser runtime suite, Composer validation, PHAR integrity checks, and the complete DB-free PHPUnit unit suite. Normal users and release maintainers do **not** need to run every historical runner separately.

The output is written to:

```text
T4A-ACCEPTANCE.log
```

A successful run must contain:

```text
Browser tests: 16, Passed: 16, Failures: 0
U319_BROWSER_ACCEPTANCE=PASS
T3B_ACCEPTANCE=PASS
OK (604 tests, 9241 assertions)
T4A_PHPUNIT12_DISCOVERY=PASS
T4A_ACCEPTANCE=PASS
```

Every `[EXIT]` line must be `0`. The final marker alone is not enough if the log was truncated or edited.

## Requirements

- Windows with Laragon, or an equivalent PHP 8.3 CLI environment
- PHP 8.3.x selected in the same terminal used to run the suite
- Required PHP extensions reported by the preflights, including JSON, mbstring, PCRE, tokenizer, and PHAR
- Microsoft Edge, Google Chrome, or Chromium for the U-3.19 browser checks
- No database, workspace, web server, Packagist access, Bitbucket access, or network access is required
- The pinned acquisition-only PHPUnit 9.5.8, 10.5.64, 11.5.49, and 12.5.35 PHARs under `tests/tools/`

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

The suite currently contains 99 PHP harness files and 604 PHPUnit tests. The cumulative U-3.19 runner has 50 stages.

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
composer.lock  913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033
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

## T-2A: PHP 8.2 discovery with PHPUnit 10

The PHP 8.2 compatibility branch starts from accepted T-1 commit `c2793bed56d3cb1b71895b407ba822f86e827d27`.
It keeps the pinned PHPUnit 9.5.8 historical lane and pinned PHPUnit 10.5.64 clean lane.

Run the focused PHP 8.2 discovery first:

```bat
tests\tools\run-t2a-php82-checks.cmd
```

If it passes, run the cumulative gate:

```bat
tests\tools\run-t2a-checks.cmd
```

The cumulative runner executes every U-3.19 historical preflight, Composer check, all 16 browser cases and PHPUnit 9 before the strict PHPUnit 10 PHP 8.2 lane. Return `T2A-PHP82-DISCOVERY.log`, `T2A-ACCEPTANCE.log`, `U319-ACCEPTANCE.log`, and `git status --short`. T-2A is discovery only; do not commit until its findings are reviewed.

## T-2B rev E: PHP 8.2 Composer platform closure

- Production lock SHA-256: `9f879af7b047666ee70708741d74521c91925e1b6addd80a9d465b6ea76e9cb3`.
- Minimal lock updates: `nette/schema` v1.2.2 -> v1.2.5 and dev-only `phpspec/prophecy` v1.15.0 -> v1.16.0.
- `nette/utils` remains v3.2.8 to preserve the PHP 8.1/8.2 dual lane.
- Acceptance remains `tests\tools\run-t2a-checks.cmd`; it runs every historical preflight, Composer validation/platform checks, browser tests, PHPUnit 9 and the PHP 8.2 PHPUnit 10 focused lane.

## T-2B rev F: non-mutating cumulative oracle lifecycle

`run-u319-checks.cmd` snapshots the frozen locale oracle before the live PHP 8.1/8.2 capture, executes every historical preflight against that live evidence, then restores the frozen fixture before browser, Composer, PHPUnit 9 and downstream PHPUnit 10 gates. The failure path restores it as well. This keeps standalone and cumulative acceptance deterministic without weakening the PHP 8.1 oracle contract test.


## T-3A: pinned PHPUnit 11 discovery lane

`run-t3a-checks.cmd` runs the complete historical U-1 through U-3.19 acceptance chain, the PHP 8.2/PHPUnit 10 lane, and then PHPUnit 11.5.49 using `phpunit-11.xml`. The PHPUnit 11 PHAR is acquired separately by `acquire-phpunit11.ps1` and verified against the pinned SHA-256 manifest. The acceptance ratchet remains exactly 604 tests and 9241 assertions.

### T-3A rev B: additive harness ratchet and clean oracle capture

The PHPUnit 11 verifier is the 93rd PHP harness file, so both historical preflights ratchet exactly 93 files. The standalone locale-oracle generator loads `Tests\Support\LegacyUtf8Oracle` and does not execute deprecated native `utf8_encode()` on PHP 8.2.

### T-3A rev C: dual-compatible data-provider metadata

The five data-provider tests reported by PHPUnit 11 now carry `PHPUnit\Framework\Attributes\DataProvider` metadata. Their existing `@dataProvider` annotations remain as the PHPUnit 9.5.8 compatibility path. PHPUnit 11 gives attribute metadata precedence, eliminating its test-runner deprecations without suppressing the deprecation gate or changing test cases, providers, assertions, production code, or suite scope.


## T-3B: acquisition-only PHPUnit runtime artifacts

The PHPUnit 9.5.8, 10.5.64, and 11.5.49 PHAR binaries are runtime-only artifacts and are no longer tracked. Their official HTTPS URLs, exact versions, and SHA-256 values are pinned by acquisition scripts and checksum manifests. Run `tests\tools\apply-t3b-acquisition-only.cmd` once to remove the historical PHPUnit 9/10 binaries from the Git index while preserving local copies, then run `tests\tools\run-t3b-checks.cmd`. The T-3B runner reacquires and verifies all three PHARs before executing every historical preflight, browser test, Composer gate, and the exact 604-test/9241-assertion PHPUnit 9/10/11 lanes.


## R-2: PHP 8.2 production release gate

`run-r2-release-checks.cmd` first executes the complete T-3B chain: every historical preflight, Composer validation, all 16 browser tests, and the exact 604-test/9241-assertion PHPUnit 9/10/11 lanes. It then builds a production ZIP from committed `HEAD` and verifies its content and SHA-256.

```bat
tests\tools\run-r2-release-checks.cmd
```

Generated artifacts are written under `build\releases\` and ignored by Git. The production archive excludes `tests/`, every `phpunit*.xml`, every PHAR binary, acceptance/discovery logs, and CI/VCS/editor metadata. It retains the accepted production `composer.json`, `composer.lock`, tracked `vendor/` tree, application sources, and an embedded release manifest recording the exact source commit.

Return `R2-ACCEPTANCE.log`, `T3B-ACCEPTANCE.log`, the generated ZIP `.sha256` file, `git status --short`, and the output of `git rev-parse HEAD`. Do not tag or publish until archive inspection and Laragon smoke testing pass.

## S-1: PHP 8.2 installer smoke-fix gate

S-1 aligns the installer requirement gate with the accepted PHP 8.2 runtime. `InstallerModule` now accepts PHP 7.4 through PHP 8.2.x and rejects PHP 8.3+ until the dedicated PHP 8.3 compatibility phase. The implementation compares the captured version string directly with `version_compare`; it no longer performs unused, lossy float parsing. English PO, compiled-language, and fresh-install SQL labels all identify PHP 8.2 as the recommended version.

Run the cumulative smoke-fix gate before commit:

```bat
tests\tools\run-s1-smoke-checks.cmd
```

It reacquires/verifies pinned PHPUnit runtimes and runs the complete T-3B historical chain: all preflights, Composer checks, 16 browser cases, and the exact PHPUnit 9/10/11 lanes before the S-1 installer matrix verifier. After committing, run `tests\tools\run-r2-release-checks.cmd` to build and verify a new commit-bound PHP 8.2 production archive containing the smoke fix.

The S-1 source verifier also confirms that cURL, SOAP, and LDAP checks remain capability-based. A red result for those entries therefore means the active Apache PHP SAPI did not load the extension; it must not be bypassed in application code.

### Laragon Apache extension repair

The observed PHP 8.2.33 CLI loads cURL, but Apache resolves the older `nghttp2.dll` beside `httpd.exe` before the PHP runtime copy. `repair-laragon-php82-apache.ps1` requires Apache to be stopped, verifies the selected PHP is 8.2.x, backs up Apache's DLL and the active `php.ini`, copies only PHP 8.2's `nghttp2.dll`, enables cURL/SOAP/LDAP/ZIP without duplicate active directives, runs `httpd.exe -t`, verifies CLI extension loading, and restores both backups on failure. It does not copy OpenSSL DLLs.

Run only after **Laragon > Stop All**:

```bat
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tests\tools\repair-laragon-php82-apache.ps1
```

After `S1_LARAGON_REPAIR=PASS`, start Laragon and verify the installer through Apache. The web-SAPI check is authoritative; CLI success alone does not prove Apache loaded the same DLL set.



## T-4A: PHP 8.3 discovery with pinned PHPUnit 12

`run-t4a-checks.cmd` is the cumulative PHP 8.3 discovery runner. It first acquires the exact PHPUnit 12.5.35 PHAR, executes the complete T-3B historical chain (all preflights, Composer checks, 16 browser cases, and PHPUnit 9/10/11), and then runs the same 604-test/9241-assertion unit scope with PHPUnit 12 using `phpunit-12.xml`.

The PHPUnit 12 runtime is acquisition-only: `phpunit-12.phar` is ignored and must never be committed. `acquire-phpunit12.ps1` downloads only the official versioned HTTPS endpoint and verifies SHA-256 `2c076d3d30f3bca762b13d996ad665d23220bc29afdb98a40387f7896b324195` before installation. The Composer development baseline remains PHPUnit 9.5. T-4B supersedes the discovery lock with the reviewed minimal PHP 8.3 closure.

T-4A deliberately extends historical compatibility preflight bounds through PHP 8.3 while leaving the S-1 and R-2 PHP 8.2 release gates exact. The added verifier raises the harness parse ratchet from 96 to 97. Run `tests\\tools\\run-t4a-checks.cmd` and return `T4A-ACCEPTANCE.log`, `T4A-PHPUNIT12-DISCOVERY.log`, `T3B-ACCEPTANCE.log`, and `git status --short`. T-4A is discovery-only; do not commit until the result is reviewed.


## T-4B: PHP 8.3 Composer dependency closure

T-4B accepts a minimal two-entry lock update: production `nette/utils` v3.2.8 -> v3.2.10 (`>=7.2 <8.4`) and development-only `phpspec/prophecy` v1.16.0 -> v1.18.0 (adds PHP 8.3). `nette/schema` remains v1.2.5, Composer `phpunit/phpunit` remains 9.5.0, and `composer.json` remains byte-identical with production PHP `>=7.4`.

Accepted `composer.lock` SHA-256: `913f83c278ba95912c1c0498a86033f01ce62d6a3501798e254cfda62c573033`. Run `tests\tools\run-t4b-checks.cmd`; it executes the full historical/browser/Composer/PHPUnit 9/10/11/12 chain before exact closure checks. Development vendor trees must remain untracked. Production archives still exclude `tests/`, `phpunit*.xml`, PHARs, logs, and Git/CI metadata.


## T-4C: PHP 8.3 runtime baseline closure

T-4C updates only two DB-free tests so the approved compatibility interval is PHP 8.1.x through PHP 8.3.x, with PHP 8.4+ still rejected. The frozen UTF-8 fixture and oracle comparisons are unchanged; the runtime gate now permits those assertions to execute on PHP 8.3. No production code or dependency metadata changes. Run `tests\tools\run-t4c-checks.cmd`, which executes the complete T-4B historical/browser/Composer/PHPUnit 9/10/11/12 chain before the exact T-4C verifier.


## T-4D: PHP 8.3 installer boundary

T-4D follows the fully green T-4C CLI chain and the Apache/Laragon pre-installation smoke check on PHP 8.3.33. It advances only the installer exclusive ceiling from PHP 8.3 to PHP 8.4, so PHP 7.4 through PHP 8.3 are accepted while PHP 8.4+ remains rejected. The English installer recommendation is updated from PHP 8.2 to PHP 8.3 in the PO source, fresh-install SQL, and compiled English catalog.

Run `tests\tools\run-t4d-checks.cmd`. It executes the complete historical/browser/Composer/PHPUnit 9/10/11/12 T-4C chain first, then validates exact installer and translation hashes, the PHP support matrix, capability-based extension checks, and preservation of the historical S-1/R-2 PHP 8.2 records. After it passes, repeat the browser pre-installation check and confirm that PHP 8.3.33 is accepted.
