---
estado: completado
verificacion: obligatoria
---

# Standalone Twig and CodeIgniter 4 adapter

> **Ledger canónico** de progreso. User authorization: implement the audit improvements
as a standalone Twig library and optional CodeIgniter 4 integration. The user also
requires no references to unrelated libraries in the implementation or docs.
The standalone core and included adapter target version 4.0. The user subsequently clarified that distribution must remain one Composer package and authorized pushing the code; creating releases remains out of scope.

## Resumen de progreso

| Campo | Valor |
|---|---|
| **Estado** | completado |
| **Fecha** | 2026-10-30 |

| Fase | Completadas | Total |
|---|---|---|
| Fase 1 | 6 | 6 |

## Fase 1 — Implementation

### T-01 — Reproduce the reported defects
- **Estado**: completado
- **Archivos**: `tests/Twig/TwigRegressionTest.php`, `tests/Standalone/CoreTest.php`, `tests/bootstrap.php`
- **Verificacion**: `php vendor/bin/phpunit --bootstrap tests/bootstrap.php --no-coverage --no-logging tests/Twig/TwigRegressionTest.php` — three failures observed before implementation.
- **Changelog**: Regression tests exercise real compiled files, initial warmup and saved render parameters.
**Criterios de aceptación**:
- [x] Reproduce cache invalidation, fresh warmup, saved render data and nested discovery defects.
- **Notas**: Initial failing assertions: invalidate removed 0 instead of 1; warmup returned zero; saved data rendered missing. Separate diagnostic reproduced a missing nested template with the root mtime unchanged.

### T-02 — Separate core and optional adapter
- **Estado**: completado
- **Archivos**: `src/**`, `composer.json`, `tests/**`, `phpunit*.xml*`
- **Verificacion**: `php tests/Consumers/standalone/smoke.php` — standalone consumer OK, CodeIgniter absent; `php tests/Consumers/codeigniter/smoke.php` — service/helper/Spark discovery OK.
- **Changelog**: Standalone configuration and runtime are available separately from the CodeIgniter adapter.
**Criterios de aceptación**:
- [x] Separate the framework-independent core from its included CodeIgniter adapter, with explicit configuration, cache and logging boundaries.
- **Notas**: Both consumers were installed with Composer --no-dev and executed with PHP 8.2.13, 8.3.30, 8.4.18 and 8.5.12. Framework configuration, helpers, commands, toolbar and compiled-cache adapter moved to the optional adapter. Namespace migration is explicit in docs/MIGRATION.md.

### T-03 — Correct cache, discovery and rendering behavior
- **Estado**: completado
- **Archivos**: `src/**`, `tests/**`
- **Verificacion**: `composer test` — standalone and adapter regression suites pass; targeted tests verify zero writes on discovery hits and no production filter lookup.
- **Changelog**: Correct cache invalidation, source warmup, saved data and nested discovery; avoid redundant discovery writes and production toolbar work.
**Criterios de aceptación**:
- [x] Fix the reproduced defects and avoid redundant snapshot writes and production toolbar work.
- **Notas**: Shared atomic metadata repository, native removable-cache keys, generation-based remote clearing with bounded payload retention, per-instance index loading and one warmup notification. Runtime namespace changes are intentional for the new major release.

### T-04 — Verify quality gates and package consumers
- **Estado**: completado
- **Archivos**: `tests/**`, `.github/workflows/**`, `.gitignore`, `.php-cs-fixer.dist.php`, `phpstan*.neon*`, `rector.php`, `deptrac.yaml`, `composer.json`, `phpunit*.xml*`, `CONTRIBUTING.md`
- **Verificacion**: `composer ci` — style, duplicate detection, static analysis, both test suites and architecture check pass.
- **Changelog**: CI verifies standalone and CodeIgniter consumers independently and enforces the core/framework boundary.
**Criterios de aceptación**:
- [x] Verify standalone consumers without CodeIgniter, adapter discovery, regression tests, analysis and style.
- **Notas**: Deptrac migration to the maintained deptrac/deptrac 4.7.2 is installed. Architecture run: 0 violations, 0 warnings. No application source paths may be excluded from analysis. Third-party/generated consumer directories are excluded only to prevent analyzing their installed dependencies and generated caches. Existing .claude files are unrelated pre-existing state and remain untouched.

### T-05 — Document installation and migration
- **Estado**: completado
- **Archivos**: `README.md`, `CHANGELOG.md`, `docs/**`, `CONTRIBUTING.md`
- **Verificacion**: Manual source/documentation reference check — no cross-library references; isolated consumer examples execute successfully.
- **Changelog**: Document the upcoming 4.0 core/adapter separation, namespace migration, cache retention and deployment-versioned discovery.
**Criterios de aceptación**:
- [x] Update installation, migration, cache documentation and changelog.
- **Notas**: Product documentation describes Twig exclusively.

### T-06 — Keep the adapter inside the single Composer package
- **Estado**: completado
- **Archivos**: `src/**`, `composer.json`, `tests/**`, `.github/workflows/**`, `.php-cs-fixer.dist.php`, `phpstan.neon.dist`, `rector.php`, `deptrac.yaml`, `phpunit*.xml*`, `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `docs/**`
- **Verificacion**: `composer ci`: exit 0 after the layout correction (137 tests / 296 assertions, PHPStan clean, no clones, architecture 0 violations). Both isolated consumers passed with PHP 8.2.13, 8.3.30, 8.4.18 and 8.5.12.
- **Changelog**: The CodeIgniter adapter is included in daycry/twig under src/Adapter/CodeIgniter4; no second Composer package or repository is required.
**Criterios de aceptación**:
- [x] Keep one public Composer package and repository, preserve standalone installation without CodeIgniter, and discover the included adapter in CI4 applications.
- [x] Prepare the validated code for upload to the existing GitHub repository.
- **Notas**: Explicit user clarification supersedes the earlier two-package interpretation. The adapter namespace remains Daycry\Twig\CodeIgniter4 with an explicit production PSR-4 prefix for framework discovery. Consumer path repositories hash their configuration so autoload metadata refreshes before a Git commit. Both isolated consumers passed after this correction.

## Revisión de dos lentes — intento 1: implementación — corregido

Lenses A+B+C+D ran through generic fresh-context reviewers (native reviewer role
unavailable). Scope check returned zero files outside the declared scope.

| Finding | Degree | Disposition and evidence |
|---|---|---|
| A: PHPCPD workflow omitted moved adapter | Important | Fixed `.github/workflows/phpcpd.yml`; adapter is included alongside core/tests. |
| A: unrelated comparative comment | Minor | Removed comparison and used generic PSR-3 wording in historical notes. |
| B: reset lost dynamic functions/filters | Important | Observed red runtime failure; reset now replays registrations. `testResetReappliesDynamicFunctionsAndFilters` passes. |
| B: initialize retained configured filesystem paths | Important | Observed red old/new source mismatch; default filesystem loaders are rebuilt. `testInitializeReplacesConfiguredFilesystemPaths` passes. |
| B: whole-cache clear retained loaded PHP classes/includes | Existing behavior gap | Observed red stale include; managed environment revision refreshes loaded classes. `testClearCacheRefreshesLoadedTemplatesAndIncludes` passes. |
| B: listTemplates filters | Discarded | Repro succeeds and current method contains both fast-path and filtered branches. |
| C: introduced security vulnerability | None | Existing HMAC/fallback behavior was compared with HEAD; no introduced/reopened vulnerability validated. |
| D: generation GET for every template | Important | Observed 50 GETs for 50 keys; memoized generation plus operation refresh now requires one GET. `testGenerationLookupIsSharedAcrossTemplateKeys` passes. |

Additional lifecycle tests verify embedded templates/globals after reset and
remote generation changes both in an existing renderer and a fresh renderer in
the same PHP worker. A fresh-renderer stale-class case was observed red before
adding the backend generation to the compiled-class revision; its regression is green.

## Revisión de dos lentes — intento 2: T-03/T-04 — corregido

| Lens | Degree | Verdict and evidence |
|---|---|---|
| A | None | No pending requirements or new scope gaps after workflow/documentation fixes. |
| B | Important | Targeted invalidation used the old memoized generation. All targeted operations now refresh before obtaining keys; `testInvalidationRefreshesTheActiveRemoteGeneration` passes. |
| B | Important | A fresh filesystem renderer in the same worker retained the previous loaded class after clear. Persistent filesystem generation and class decoration fix this; standalone regression passes. |
| C | None | No introduced/reopened vulnerabilities in generation memoization or hashed class revisions. Existing HMAC behavior remains outside this finding scope. |
| D | None | Memoization removes repeated generation lookups; 50 keys use one GET. |

Additional verification covers moving metadata when enabling a cache directory,
warmup after local reset using the shared class, and diagnostic reset through the
configured backend. Reset does not delete generation markers. CacheStore deletion
is idempotent with the real CodeIgniter FileHandler (red observed, then green).
An existing worker now reuses a template warmed after external generation clear
without an additional compiled write; the source mtime is controlled in this test
to separate reuse from Twig's same-second auto-reload semantics.

## Revisión de dos lentes — intento 3: T-03/T-04 — corregido

Lenses A+B+C+D used fresh generic reviewers; the native reviewer role is not
available. No fourth general review was opened.

| Lens | Degree | Verdict and evidence |
|---|---|---|
| A | None | Requirements, scope, documentation and gate strength remain aligned; T-03/T-04 remain open until final QA. |
| B | Important | `StateRepository::clear()` ignored delete=false, so reset with include-cache reported success. The orchestrator chose a focused correction: attempt all known metadata deletions and throw on any failure; the existing reset API catches/logs the failure and returns ok=false. Regression observed red before the fix, then green (1 test / 2 assertions). |
| C | None | No introduced vulnerability in marker paths, atomic writer, hashed class identities, canonical warmup or backend reset. |
| D | None | 50 keys still use one generation GET; refresh is outside template loops; shared warmup reused without another compiled write. Remote tests 8/20 assertions and focused standalone tests 6/15 passed. |

The final B finding is resolved by the focused regression and final QA rather
than a fourth general review. No remaining Critical/Important gaps are known.
Jira is not configured in this repository, so no review events were published.

## Final QA evidence

- `composer ci`: exit 0 with both generated consumer vendor directories absent
  for the entire run (restored afterwards). Style: 0/92 files needing changes;
  PHPCPD: no clones; PHPStan level 5: no errors without a baseline.
- PHPUnit: standalone 52 tests / 118 assertions; CodeIgniter 85 tests / 178
  assertions. Total: 137 tests / 296 assertions, all passing.
- Deptrac: 0 violations, 0 skipped violations, 0 warnings, 0 errors. The 101
  uncovered dependencies are unassigned external classes, not suppressed edges.
- Rector: the full configured sources were processed; final changed-file dry runs
  report no remaining changes. Its legacy rule deprecation remains visible.
- Both isolated consumers passed on PHP 8.2.13, 8.3.30, 8.4.18 and 8.5.12.
  Standalone confirms CodeIgniter classes/helpers/constants are absent. Adapter
  confirms service/helper/Spark discovery and executes the backend diagnostic reset.
- Targeted regression for metadata deletion failure: red before implementation,
  green afterwards (1 test / 2 assertions); final full suites include it.
- No external publication or messages were performed. Release remains 4.0 pending
  publication; namespace migration is documented.

Nonblocking tool notices remain visible: the bundled PHPCPD PHAR uses a deprecated
nullable signature on PHP 8.5, CS Fixer recommends the minimum target interpreter,
and the integration timing extension reports slow tests on this machine. No gate
was disabled or threshold relaxed to hide these notices.

## User clarification after QA

The earlier two-package layout was an implementation interpretation, superseded
by the user's clarification to include the CodeIgniter adapter in the same
Composer package. T-06 records that correction. Previous review/QA results refer
to the earlier layout; final checks for the corrected layout are recorded below.

Final single-package QA: `composer ci` exit 0. A collector regression initially
retained the previous directory in its file-existence assertion; that path now
matches src/Adapter/CodeIgniter4, preserving the assertion. The corrected full
suite passes. Adapter service/helper/Spark discovery and reset command also pass
in the isolated single-package consumer on all four supported PHP versions.
No additional Composer package, repository, tag or release is required for this
layout. The requested Git upload is performed after recording this evidence.
