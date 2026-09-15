# Offline Acceptance Gate Guide — U-1 R5 on Windows + Laragon

This unit only adds the test safety net; it does not change any engine runtime file, core dependency, database, route, process, or existing trigger.

## Why was R5 built?

On the R4 run under PHP 8.1.10, for the first time, all gates opened up to PHPUnit itself:

```text
[SUMMARY] 27 preflight checks passed.
PHPUnit 9.5.8 by Sebastian Bergmann and contributors.
[SUMMARY] 3 PHPUnit PHAR integrity checks passed.
Tests: 17, Assertions: 99, Failures: 2.
```

In other words, the PHAR problem on PHP 8.1 was solved and all 17 tests actually ran. But two compatibility tests went red:

- `ProcessMaker\Core\System` appeared to have lost 12 public methods;
- 45 global trigger functions (`PMF*` and helpers) appeared to have vanished.

**The root cause was not in the engine; it was in the test scanner itself.** PHP opens the strings `"{$var}"` and `"${var}"` with the `T_CURLY_OPEN` and `T_DOLLAR_OPEN_CURLY_BRACES` tokens, but closes the same brace with a plain `}` token. The R4 scanner only counted `}`; as a result, from the very first string interpolation the brace depth dropped by one, the class scope closed early, and every subsequent definition was reported as "removed."

Evidence for this diagnosis: `System.php` has three and `class.pmFunctions.php` has two string interpolations; simulating the same algorithm exactly reproduces the 12 methods and 45 functions reported in the owner's log, and after fixing the token counting, all 45 `System` methods and all 103 global functions are found again. The other four classes (`Cases`, `Derivation`, `PMScript`, `WsBase`) have no string interpolation, so they were green even in R4.

## Changes in R5 vs R4

Only four text files changed; no files were added or removed, and the official PHPUnit 9.5.8 PHAR is byte-for-byte identical to before.

| File | Change |
| --- | --- |
| `tests/Support/PhpSourceScanner.php` | Correct counting of `T_CURLY_OPEN` and `T_DOLLAR_OPEN_CURLY_BRACES` in both scanners |
| `tests/unit/Architecture/PublicApiCompatibilityTest.php` | Added a string-interpolation guard test + report found-counts in the failure message |
| `tests/tools/run-u1-checks.cmd` | Version label only: R4 → R5 |
| `tests/tools/README.md` | This guide |

As a result, the test count rises from 17 to **18**; the eighteenth test locks the scanner itself so this class of error cannot silently return.

## Trusted PHAR identity (unchanged)

| Item | Value |
| --- | --- |
| Official source | `https://phar.phpunit.de/phpunit-9.5.8.phar` |
| Version | `PHPUnit 9.5.8` |
| Size | `4,458,067 bytes` |
| Official SHA-256 | `11f27cf3f9522241fe234e9bf5813667207a074ac92089aac26d502ffc5e9517` |

## Target version

- Target PHP: **8.1.x**; the owner's current environment is PHP 8.1.10 on Laragon.
- Full backward compatibility is preserved for all processes and triggers.
- `composer.lock` and the dependency fixture still record `phpunit/phpunit=9.5.0`; the PHAR is only the offline acceptance runner.

## Installing R5

Use **only one** of the following methods:

1. On the exact U-1 R4 source, extract the R5 package and run `APPLY-R5.cmd` with the project root path (the hash of all four input and output files is verified).
2. On the exact U-1 R4 source, apply the incremental patch `U1_R4_TO_R5_OFFLINE_ACCEPTANCE.patch`.
3. On the vanilla ProcessMaker 3.8.3 source, apply the full patch `PROCESSMAKER_3.8.3_U1_R5_FULL.patch`.

Do not combine the methods.

## Sequential run with a single log

Open the Laragon Terminal with PHP 8.1, go to the project root, and simply run:

```bat
tests\tools\run-u1-checks.cmd
```

All output is saved to a single file:

```text
U1-ACCEPTANCE.log
```

Execution order (fail-fast and fully offline):

1. Record the PHP path and version;
2. 27 preflight checks including PHP 8.1, PHPUnit extensions, `ext-phar`, the U-1 structure, and the PHAR file's SHA-256;
3. Record Composer, `composer validate`, and `composer check-platform-reqs --lock --no-dev` without installing any packages;
4. Record the PHAR version;
5. Re-verify the SHA-256 immediately before the suite;
6. Run the 18 PHPUnit 9.5.8 tests without a DB.

A successful run must show all of these markers:

```text
[SUMMARY] 27 preflight checks passed.
PHPUnit 9.5.8 by Sebastian Bergmann and contributors.
[SUMMARY] 3 PHPUnit PHAR integrity checks passed.
OK (18 tests, ... assertions)
U1_ACCEPTANCE=PASS
```

Return the new `U1-ACCEPTANCE.log`. Take a look at it before sending; the script asks for no passwords or tokens and sends nothing from your environment.

## If a gate stops

- A `Missing PHPUnit ... extensions` message means that extension must be enabled in the `php.ini` of that same PHP 8.1; then open a new terminal.
- A SHA-256 error means the PHAR was copied incompletely; re-apply the R5 package.
- Legacy warnings in `composer validate` are not a failure as long as `[EXIT] 0` is recorded.
- If a test still goes red, the failure message now prints the found counts too; for example, "33 of 45" means a scanning problem, but "44 of 45" means an API really was removed.
- No `composer install`, download, Packagist, or Bitbucket is needed for this gate.

## U-1's 18-test coverage

- Locks the public API of `Derivation`, `Cases`, `PMScript`, `WsBase`, and `ProcessMaker\Core\System`;
- Locks 55 `PMF*` functions and the other global trigger functions;
- A string-interpolation guard for the scanner itself (new in R5);
- Locks the `composer.lock` identity and the four private dependencies;
- A decreasing budget for legacy PHP APIs and `eval`;
- Four behavioral tests for `ProcessMaker\Util\ArrayUtil`;
- A bootstrap with no Laravel, Artisan, workspace, or DB.

## Acceptance boundary

Static packaging checks are no substitute for a real Windows/PHPUnit run. That boundary belonged to the U-1 delivery; the current U-2.1 status appears in the next section. Stage 24 remains frozen.


# U-2.1 — PHP 8 executable ledger and six low-risk fixes

This is the first unit that changes engine production code, but its scope is deliberately kept small and reviewable:

- Four legacy interpolations converted from the `${name}` form to the equivalent `{$name}` form;
- Two uses of the removed `$php_errormsg` variable replaced with `error_get_last()` and an empty-string fallback;
- The text of two Exceptions and the success behavior of the network-read path are untouched;
- `composer.json`, `composer.lock`, dependencies, and the application's real bootstrap are unchanged.

The new ledger counts only executable PHP and excludes comments, regex text, inline HTML, and JavaScript stored inside strings. The raw U-1 ledger is preserved unchanged.

## Running U-2.1 acceptance on Windows/Laragon

```bat
cd /d "D:\laragon\www\PM3Infinity\pm3InfinityCore\processmaker"
tests\tools\run-u2-checks.cmd
```

The script is fail-fast, fully offline, and has 11 stages. All stdout/stderr is saved to `U2-ACCEPTANCE.log`. A successful run must show these markers:

```text
[SUMMARY] 33 preflight checks passed.
[SUMMARY] 53 U-2.1 preflight checks passed.
PHPUnit 9.5.8 by Sebastian Bergmann and contributors.
[SUMMARY] 3 PHPUnit PHAR integrity checks passed.
OK (33 tests, ... assertions)
U2_ACCEPTANCE=PASS
```

All eleven `[EXIT]` lines must also be `0`. Because it scans 1,798 PHP files, this run takes longer than U-1.

## Next order and acceptance boundary

- The real `ereg*` count was zero; that empty unit was removed.
- After explicit acceptance: U-2.2 for `utf8_encode`/`utf8_decode`, then U-2.3 for `strftime`/`strptime`.
- The 15 `mcrypt_*` and 9 `FILTER_SANITIZE_STRING` occurrences are deliberately deferred to separate higher-risk units.
- U-2.1 closes only after a successful log containing `OK (33 tests, ...)` and `U2_ACCEPTANCE=PASS`, followed by the owner's explicit approval. Stage 24 is frozen.


# U-2.2.1 — UTF-8 compatibility contract and oracle (no call-site migration)

This unit only freezes the byte-level contract of legacy behavior and adds a pure-PHP compatibility helper. The helper is deliberately **not used** in this unit, and all 12 existing executable call sites (10 encode and 2 decode) remain unchanged. Changing call sites is only permitted in a separate unit and after explicit acceptance of U-2.2.1.

## New files and their rationale

- `workflow/engine/src/ProcessMaker/Util/LegacyUtf8.php`: an implementation independent of extensions and global state for the exact PHP 8.1 contract; has no production consumer.
- `tests/fixtures/legacy-utf8-contract.json`: a hexadecimal oracle covering all 256 encode input bytes and 46 valid/invalid decode cases.
- `tests/unit/Compatibility/LegacyUtf8ContractTest.php`: six DB-free tests including matching against the native oracle on PHP 8.1, round-trip, and no change to mbstring-related state.
- `tests/tools/verify-u22.php`: an independent preflight for the fixture, the helper, unchanged dependencies, and the 12 unchanged call sites.
- `tests/tools/run-u22-checks.cmd`: the owner's offline, fail-fast acceptance runner.

`tests/tools/verify-u1.php` was updated only to recognize the new harness files and to raise the parse count from 14 to 16. This unit does not change `composer.json`, `composer.lock`, the schema, the public API, the application bootstrap, or any existing call site.

## Running U-2.2.1 acceptance on Windows/Laragon

From the accepted project root, run:

```bat
cd /d "D:\laragon\www\PM3Infinity\pm3InfinityCore\processmaker"
tests\tools\run-u22-checks.cmd
```

The runner is fully offline, fail-fast, has 12 stages, and only writes `U22-ACCEPTANCE.log` at the project root. A successful run must show these markers:

```text
[SUMMARY] 37 preflight checks passed.
[SUMMARY] 53 U-2.1 preflight checks passed.
[SUMMARY] 35 U-2.2.1 preflight checks passed.
[SUMMARY] 3 PHPUnit PHAR integrity checks passed.
PHPUnit 9.5.8 by Sebastian Bergmann and contributors.
OK (39 tests, ... assertions)
U22_ACCEPTANCE=PASS
```

All 12 `[EXIT]` lines must be `0`. Return the `U22-ACCEPTANCE.log` unchanged. Until this log is reviewed and explicitly accepted by the owner, U-2.2.1 is not closed and U-2.2.2 must not begin.

## Technical boundary of the contract

- encode maps each of the 256 ISO-8859-1 bytes to its exact UTF-8 mapping.
- decode converts only code points U+0000 through U+00FF back to bytes; larger code points and invalid sequences are replaced with `?` per the official PHP 8.1 advancement.
- The helper uses no native converters, `mb_convert_encoding`, `iconv`, substitution settings, DB, network, or the application bootstrap.
- Hits inside PMScript-generated strings and the date/locale unit remain separate risks and are unchanged in this unit.

