<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

final class A03PluginAdvisories extends Check
{
    public function id(): string
    {
        return 'GG-A03';
    }

    public function name(): array
    {
        return L::t('Installed plugins vs. published security advisories', 'Kurulu eklentiler ve yayımlanmış güvenlik duyuruları');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $out = [];
        foreach ($g->rows('SELECT directory, name, version, state FROM glpi_plugins ORDER BY directory') as $p) {
            $dir = (string) $p['directory'];
            $hits = $adv->forPlugin($dir, (string) $p['version']);
            if (!$hits) {
                continue;
            }
            $sev = Finding::LOW;
            foreach ($hits as $a) {
                if ((Finding::ORDER[$a['severity']] ?? 0) > Finding::ORDER[$sev]) {
                    $sev = $a['severity'];
                }
            }
            $active = (int) $p['state'] === 1;
            if (!$active && Finding::ORDER[$sev] > Finding::ORDER[Finding::MEDIUM]) {
                $sev = Finding::MEDIUM; // code present but disabled: still worth fixing, lower urgency
            }
            $safe = Advisories::minimumSafe($hits, (string) $p['version']);
            $out[] = $this->finding(
                $sev,
                L::f(L::t('Plugin %s %s is affected by %d security advisories', '%s %s eklentisi %d güvenlik duyurusundan etkileniyor'), $dir, $p['version'], count($hits)),
                $active
                    ? L::t('The plugin is active.', 'Eklenti etkin.')
                    : L::t('The plugin is not active, but its code is still on the server.', 'Eklenti etkin değil ama kodu hâlâ sunucuda.'),
                $safe !== null
                    ? L::f(L::t('Update the plugin to %s or later, or remove it if unused.', 'Eklentiyi en az %s sürümüne güncelleyin; kullanılmıyorsa kaldırın.'), $safe)
                    : L::t('Update the plugin to its latest version, or remove it if unused.', 'Eklentiyi en son sürüme güncelleyin; kullanılmıyorsa kaldırın.'),
                array_map(static fn($a) => sprintf('[%s] %s%s — %s', strtoupper($a['severity']), $a['id'], $a['cve'] ? ' / ' . $a['cve'] : '', $a['summary']), array_slice($hits, 0, 10)),
                array_map(static fn($a) => $a['url'], array_slice($hits, 0, 5))
            );
        }
        return $out;
    }
}
