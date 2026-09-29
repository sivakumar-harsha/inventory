# Release 1.5.1 — Export Infrastructure Patch: Acceptance Baseline

Date: 2026-08-27
Scope: Infrastructure-only fix for Excel/PDF export across Reports and Projects modules.

## 1. Composer Packages Installed

| Package | Version | Reason |
|---|---|---|
| `phpoffice/phpspreadsheet` | **5.9.0** | Excel (XLSX) export engine used by `Reports.php` and `Projects.php` |
| `dompdf/dompdf` | **v3.1.6** | PDF export engine used by `Reports.php` and `Projects.php` |

Installed via `composer require phpoffice/phpspreadsheet dompdf/dompdf`, letting Composer resolve all transitive dependencies (no manual vendoring). Key transitive packages pulled in automatically:

- `masterminds/html5` — required by Dompdf's HTML parser (previously missing entirely)
- `sabberworm/php-css-parser` — required by `dompdf/php-svg-lib` (previously missing entirely)
- `dompdf/php-font-lib`, `dompdf/php-svg-lib`
- `psr/simple-cache`, `maennchen/zipstream-php` (3.1.2), `markbaker/complex`, `markbaker/matrix` — PhpSpreadsheet dependencies

**Important deployment note (see §5):** the first `composer require` was run against the machine's PATH-default PHP (CLI 8.5.5) and resolved `maennchen/zipstream-php` 3.2.2, which requires PHP ≥8.3. This broke the site (HTTP 500 on every route) because Apache's mod_php on this XAMPP install is PHP **8.2.12**. Fixed by re-running `composer require ... --with-all-dependencies` using XAMPP's own `php.exe`, which correctly resolved `zipstream-php` down to 3.1.2 (PHP 8.2-compatible). This is now reflected in `composer.json`/`composer.lock`. See Deployment Instructions for how to avoid this on the Plesk server.

## 2. Files Modified

| File | Change |
|---|---|
| `composer.json` | Added `dompdf/dompdf: ^3.1` and `phpoffice/phpspreadsheet: ^5.9` to `require` |
| `composer.lock` | Regenerated to lock the resolved dependency tree |
| `vendor/` | Populated via Composer (not committed manually) |

**No changes to:** `app/Controllers/Reports.php`, `app/Controllers/Projects.php`, `app/Models/*`, `app/Views/*`, `app/Config/Autoload.php`, `app/Config/Routes.php`, or `app/ThirdParty/*`.

This was possible because both controllers already `use` the standard Composer-style namespaces (`PhpOffice\PhpSpreadsheet\...`, `Dompdf\Dompdf`, `Dompdf\Options`) — once those namespaces exist in `vendor/composer/autoload_psr4.php`, PHP's autoloader resolves them with zero code changes. The legacy, empty `app/ThirdParty/phpoffice/` and `app/ThirdParty/simple-cache/` directories were left in place untouched (not referenced anywhere in code, confirmed via grep) and not deleted, per instructions.

## 3. Acceptance Test — PASS/FAIL Table

Tested live against `http://localhost/aandainventory` (XAMPP, PHP 8.2.12), authenticated session, project ID 14 (has sales/purchases/returns — same project used in the Phase 5B baseline).

| Endpoint | HTTP | File Type | Size | Structural Check | Result |
|---|---|---|---|---|---|
| `reports/profit-loss-export` | 200 | XLSX | 7,079 B | `sheet1.xml` present | **PASS** |
| `reports/stock-export` | 200 | XLSX | 7,449 B | `sheet1.xml` present | **PASS** |
| `reports/ledger-export` | 200 | XLSX | 8,614 B | `sheet1.xml` present | **PASS** |
| `reports/purchases-export` | 200 | XLSX | 7,069 B | `sheet1.xml` present | **PASS** |
| `reports/sales-export` | 200 | XLSX | 7,205 B | `sheet1.xml` present | **PASS** |
| `reports/profit-loss-pdf` | 200 | PDF v1.7 | 21,454 B | 1 page (`/Count 1`) | **PASS** |
| `reports/stock-pdf` | 200 | PDF v1.7 | 23,520 B | valid PDF | **PASS** |
| `reports/ledger-pdf` | 200 | PDF v1.7 | 29,197 B | valid PDF | **PASS** |
| `projects/statement-export/14` | 200 | XLSX | 7,972 B | `sheet1.xml` present | **PASS** |
| `projects/statement-pdf/14` | 200 | PDF v1.7 | 28,745 B | 2 pages (`/Count 2`) | **PASS** |

All 10 previously-failing endpoints (`Class "PhpOffice\PhpSpreadsheet\Spreadsheet" not found`) now return HTTP 200 with a genuine, non-empty XLSX or PDF payload. No fatal errors observed.

### Currency / Date Formatting

Spot-checked `projects/statement-export/14` XLSX raw cell data:
- `Total Billed` = 1186.16 ✓ (matches DB, matches Phase 5B baseline: 1,186.16)
- `Total Purchases` = 12980.0 ✓ (matches DB, matches Phase 5B baseline: 12,980.00)
- Shared strings confirm date formatting intact: `"Generated On: 27-08-2026 04:14 PM"`, timeline row date `"2026-08-01"`.

### SQL Verification (Project 14)

| Metric | Export Value | DB/Baseline Value | Match |
|---|---|---|---|
| Total Billed | 1,186.16 | 1,186.16 | ✓ |
| Total Purchases | 12,980.00 | 12,980.00 | ✓ |
| Outstanding Collection Balance | 1,186.16 | 1,186.16 | ✓ |
| Timeline row count | matches `statement/14` view | matches `statement/14` view | ✓ |

Figures are byte-identical to the ones independently SQL-verified in `PHASE5_BASELINE.md` §2 — confirming the export path performs no recalculation and reuses the same, unmodified `ProjectModel`/`Reports` data logic.

## 4. Regression Checklist

| Module | Route(s) tested | Result |
|---|---|---|
| Dashboard | `dashboard` | PASS — 200 |
| Sales | `sales` | PASS — 200 |
| Purchases | `purchases` | PASS — 200 |
| Payments | `payments` | PASS — 200 |
| Reports (non-export views) | `reports`, `reports/profit-loss`, `reports/stock`, `reports/ledger` | PASS — 200 |
| Project Financial Tracker | `projects/view/14` | PASS — 200, KPI values unchanged |
| Project Statement (view) | `projects/statement/14` | PASS — 200, timeline/summary unchanged |
| Products / Customers / Suppliers | `products`, `customers`, `suppliers` | PASS — 200 |

No controller, model, or view files were modified in this release — regression risk is limited to the Composer dependency graph itself, which was verified above.

## 5. Deployment Instructions (Plesk)

1. **Confirm the target PHP version first.** In Plesk, check the domain's configured PHP version (Websites & Domains → your domain → PHP Settings) *before* running Composer. This project targets PHP `^8.2`.
2. If Plesk exposes multiple PHP CLI binaries (common: `/opt/plesk/php/8.2/bin/php`), always invoke Composer through the **same PHP version the site actually runs under** — e.g.:
   ```
   /opt/plesk/php/8.2/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
   ```
   Do **not** let a different system-default `php` (e.g. a CLI-only 8.3/8.4 install) resolve the lock file — this project already hit exactly that failure mode locally (see §1) and the resulting `composer.lock` would demand PHP ≥8.3, hard-breaking the site under Plesk's actual runtime.
3. Copy `composer.json` and `composer.lock` (already locked to PHP-8.2-compatible versions) to the server.
4. Run `composer install --no-dev --optimize-autoloader` (not `composer update`) so it reproduces exactly the locked versions — 5.9.0 / v3.1.6 / zipstream-php 3.1.2 — rather than re-resolving.
5. Verify required PHP extensions are enabled in Plesk's PHP settings: `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `mbstring`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`.
6. Smoke-test one export from each family post-deploy: `reports/profit-loss-export` (XLSX) and `reports/profit-loss-pdf` (PDF).

## 6. Rollback Instructions

This project is **not currently under git version control** (`git status` confirms no `.git` repository at the project root) — standard `git revert` is not available. To roll back this release:

1. Restore the pre-release `composer.json` (remove the two `require` lines for `dompdf/dompdf` and `phpoffice/phpspreadsheet`):
   ```json
   "require": {
       "php": "^8.2",
       "codeigniter4/framework": "^4.7"
   }
   ```
2. Delete `composer.lock` and the `vendor/` directory.
3. Run `composer install` to regenerate `vendor/` matching the restored `composer.json` (framework-only, no export libraries).
4. Exports will return to their prior failure state (`Class "PhpOffice\PhpSpreadsheet\Spreadsheet" not found`); all other application functionality is unaffected since no controller/model/view files were changed by this release.

**Recommended going forward:** initialize a git repository (or, if one already exists elsewhere and this checkout is simply unlinked, reconnect it) and commit `composer.json`/`composer.lock` as a tagged release point before any further dependency changes, so future rollbacks are a single `git checkout` instead of a manual file reconstruction.

## Summary

| Check | Result |
|---|---|
| Composer install (Phase 1) | PASS (after correcting PHP binary mismatch) |
| ThirdParty decoupling (Phase 2) | PASS — zero code changes needed, confirmed no references to `app/ThirdParty` in controllers/config |
| Compatibility verification (Phase 3) | PASS — all 10 endpoints, classes resolve under actual Apache PHP 8.2.12 runtime |
| Regression testing (Phase 4) | PASS — all export files structurally valid, SQL-verified, browser round-trip confirmed |
| Regression audit (Phase 5) | PASS — no business logic, schema, or non-export view changes |

**Overall: PASS.** All 10 previously-failing export endpoints are now functional. One infrastructure issue was discovered and corrected during implementation (PHP version mismatch between the CLI Composer resolved against and Apache's actual runtime) — flagged here rather than silently worked around, since it's directly relevant to safe deployment on Plesk.
