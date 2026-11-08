<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/** Backups, dumps and debug scripts left inside the GLPI code directory (where the web server may serve them). */
final class D01StrayFiles extends Check
{
    private const PATTERN = '#(\.sql|\.sql\.gz|\.sql\.zip|\.dump|\.bak|\.old|\.orig|\.swp|\.zip|\.tar|\.tar\.gz|\.tgz|\.7z|\.rar|~)$|^(phpinfo|adminer)[\w.-]*\.php$#i';
    /** generic names only suspicious at the top of the tree (plugins legitimately have e.g. front/test.php) */
    private const TOP_ONLY = '#^(info|test|i|php)\.php$#i';
    private const SKIP_DIRS = ['vendor', 'node_modules', 'files', 'lib', 'locales', 'css', 'pics', 'sound', 'js', '.git', 'tests', 'install'];

    public function id(): string
    {
        return 'GG-D01';
    }

    public function name(): array
    {
        return L::t('Backups, dumps and debug scripts in the GLPI directory', 'GLPI klasöründe yedek, döküm ve test dosyaları');
    }

    public function needsDb(): bool
    {
        return false;
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $public = [];
        $root = [];
        $this->scan($g->root(), '', 0, 3, $public, $root);
        $out = [];
        if ($public) {
            $out[] = $this->finding(
                Finding::HIGH,
                L::f(L::t('%d backup/debug files inside public/', 'public/ içinde %d yedek/test dosyası'), count($public)),
                L::t('public/ is the web root of modern GLPI: these files can be downloaded or executed by anyone who reaches GLPI.',
                     'public/ güncel GLPI\'nin web köküdür: bu dosyaları GLPI\'ye erişebilen herkes indirebilir ya da çalıştırabilir.'),
                L::t('Move backups outside the web directories and delete debug scripts (phpinfo, adminer…).', 'Yedekleri web klasörlerinin dışına taşıyın, test betiklerini (phpinfo, adminer…) silin.'),
                array_slice($public, 0, 20)
            );
        }
        if ($root) {
            $out[] = $this->finding(
                Finding::MEDIUM,
                L::f(L::t('%d backup/debug files inside the GLPI directory', 'GLPI klasöründe %d yedek/test dosyası'), count($root)),
                L::t('If the web server points to the GLPI root (older or misconfigured setups), these files are downloadable. Dumps contain password hashes and personal data.',
                     'Web sunucusu GLPI kök klasörünü gösteriyorsa (eski ya da yanlış yapılandırılmış kurulumlar) bu dosyalar indirilebilir. Dökümler şifre özetleri ve kişisel veri içerir.'),
                L::t('Move backups to a protected location outside the GLPI directory and delete leftovers.', 'Yedekleri GLPI klasörü dışında korumalı bir yere taşıyın, artıkları silin.'),
                array_slice($root, 0, 20)
            );
        }
        return $out;
    }

    private function scan(string $base, string $rel, int $depth, int $max, array &$public, array &$root): void
    {
        $dir = $base . ($rel !== '' ? '/' . $rel : '');
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') {
                continue;
            }
            $r = $rel !== '' ? $rel . '/' . $it : $it;
            $full = $dir . '/' . $it;
            if (is_dir($full) && !is_link($full)) {
                if ($depth < $max && !in_array(strtolower($it), self::SKIP_DIRS, true)) {
                    $this->scan($base, $r, $depth + 1, $max, $public, $root);
                }
                continue;
            }
            $top = $rel === '' || $rel === 'public';
            if (preg_match(self::PATTERN, $it) || ($top && preg_match(self::TOP_ONLY, $it))) {
                $size = @filesize($full);
                $line = $r . ($size !== false ? ' (' . number_format($size / 1048576, 1) . ' MB)' : '');
                if (strpos($r, 'public/') === 0) {
                    $public[] = $line;
                } else {
                    $root[] = $line;
                }
            }
        }
    }
}
