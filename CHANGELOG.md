# Changelog

## 0.1.1 — 2026-11-08

- Complete advisory database: 210 GLPI advisories (2019 → today) and 29 plugin advisories; withdrawn/placeholder advisories are skipped so they cannot raise false alarms.
- Docker image `ghcr.io/codebypinar/glpi-guard` (linux/amd64, linux/arm64), published by CI; `GG-A04` is skipped inside containers.
- Composer / Packagist package `codebypinar/glpi-guard` (`composer global require`, `composer create-project` into `plugins/glpiguard`); CI runs `composer validate --strict`.

## 0.1.0 — 2026-11-08

- **GLPI plugin** (`glpiguard`, GLPI 10 / 11 / 12): Setup > GLPI Guard page for super-admins with grade, score,
  findings and fixes, "Scan now", history of the last 100 scans with score trend, HTML/JSON report download, weekly
  automatic action `glpiguardscan`. Uses GLPI's own database connection; writes only to its own table.
- **CLI** (`bin/glpi-guard`, single-file `glpi-guard.phar`): the same engine without installing anything.
- 25 read-only checks: versions & advisories, accounts, API, files, configuration, profiles.
  Text / JSON / HTML reports in English and Turkish, score and grade, `--fail-on` for CI.
- Offline advisory database built from GitHub Security Advisories (GLPI core + 22 widely used plugins).
- Tested on GLPI 10.0.26, 11.0.8 and 12.0.0 (MariaDB 10.11, PHP 8.3).
