# Changelog

## 0.1.0 — unreleased

- **GLPI plugin** (`glpiguard`, GLPI 10 / 11 / 12): Setup > GLPI Guard page for super-admins with grade, score,
  findings and fixes, "Scan now", history of the last 100 scans with score trend, HTML/JSON report download, weekly
  automatic action `glpiguardscan`. Uses GLPI's own database connection; writes only to its own table.
- **CLI** (`bin/glpi-guard`, single-file `glpi-guard.phar`): the same engine without installing anything.
- 25 read-only checks: versions & advisories, accounts, API, files, configuration, profiles.
  Text / JSON / HTML reports in English and Turkish, score and grade, `--fail-on` for CI.
- Offline advisory database built from GitHub Security Advisories (GLPI core + 22 widely used plugins).
- Tested on GLPI 10.0.26, 11.0.8 and 12.0.0 (MariaDB 10.11, PHP 8.3).
