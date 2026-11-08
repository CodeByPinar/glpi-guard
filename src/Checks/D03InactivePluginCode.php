<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/** Plugin code present on disk while the plugin is not active (or not even installed). */
final class D03InactivePluginCode extends Check
{
    public function id(): string
    {
        return 'GG-D03';
    }

    public function name(): array
    {
        return L::t('Inactive plugin code left on the server', 'Sunucuda duran etkin olmayan eklenti kodu');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $state = [];
        foreach ($g->rows('SELECT directory, state FROM glpi_plugins') as $p) {
            $state[(string) $p['directory']] = (int) $p['state'];
        }
        $idle = [];
        foreach (['plugins', 'marketplace'] as $base) {
            foreach (@scandir($g->path($base)) ?: [] as $d) {
                if ($d === '.' || $d === '..' || $d[0] === '.' || !is_dir($g->path($base . '/' . $d)) || !is_file($g->path($base . '/' . $d . '/setup.php'))) {
                    continue;
                }
                if (($state[$d] ?? 0) !== 1) {
                    $idle[] = $base . '/' . $d . (isset($state[$d]) ? '' : ' (not installed)');
                }
            }
        }
        if (!$idle) {
            return [];
        }
        return [$this->finding(
            Finding::LOW,
            L::f(L::t('%d plugins are on disk but not active', '%d eklenti diskte duruyor ama etkin değil'), count($idle)),
            L::t(
                'Unused code still has to be patched: vulnerable files of an inactive plugin can be reachable depending on the web server setup, and they are re-enabled easily.',
                'Kullanılmayan kodun da güncel tutulması gerekir: web sunucusu yapılandırmasına göre etkin olmayan eklentinin açıklı dosyalarına erişilebilir ve kolayca yeniden etkinleştirilebilir.'
            ),
            L::t('Uninstall and delete the folders of plugins you do not use.', 'Kullanmadığınız eklentileri kaldırın ve klasörlerini silin.'),
            [Util::names($idle, 20)]
        )];
    }
}
