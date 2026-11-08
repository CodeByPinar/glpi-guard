# Security policy

## Vulnerabilities in GLPI or its plugins

glpi-guard only reports **already published** advisories. If you discover a new vulnerability in GLPI, report it
privately to the GLPI team: <https://github.com/glpi-project/glpi/security/advisories/new>
(for a plugin, use the security advisory form of that plugin's repository). Please do not open a public issue here.

## Vulnerabilities in glpi-guard

Use GitHub's private vulnerability reporting on this repository ("Security" tab → "Report a vulnerability").

## Scope of the tool

glpi-guard is a defensive, read-only audit for installations you are responsible for. It contains no exploit code,
performs no login attempts and has no feature to scan third-party systems. Contributions that add such features
will not be accepted.
