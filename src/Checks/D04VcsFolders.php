<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/** Version-control and environment files that leak source, history or secrets when served. */
final class D04VcsFolders extends Check
{
    private const NAMES = ['.git', '.svn', '.hg', '.env', '.env.local', 'composer.json~', '.DS_Store'];

    public function id(): string
    {
        return 'GG-D04';
    }

    public function name(): array
    {
        return L::t('Version-control and environment files', 'Sürüm kontrol ve ortam dosyaları');
    }

    public function needsDb(): bool
    {
        return false;
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $found = [];
        foreach (['', 'public', 'config'] as $d) {
            foreach (self::NAMES as $n) {
                if (file_exists($g->path(($d !== '' ? $d . '/' : '') . $n))) {
                    $found[] = ($d !== '' ? $d . '/' : '') . $n;
                }
            }
        }
        foreach (@scandir($g->path('plugins')) ?: [] as $p) {
            if ($p !== '.' && $p !== '..' && is_dir($g->path('plugins/' . $p . '/.git'))) {
                $found[] = 'plugins/' . $p . '/.git';
            }
        }
        if (!$found) {
            return [];
        }
        $inPublic = (bool) array_filter($found, static fn($f) => strpos($f, 'public/') === 0);
        return [$this->finding(
            $inPublic ? Finding::HIGH : Finding::LOW,
            L::f(L::t('%d version-control / environment entries in the GLPI tree', 'GLPI ağacında %d sürüm kontrol / ortam dosyası'), count($found)),
            L::t(
                'If served by the web server, .git exposes the full source history (and any secret ever committed); .env files may contain passwords.',
                'Web sunucusu bunları sunarsa .git tüm kaynak geçmişini (ve bir kez bile işlenmiş gizli bilgileri), .env dosyaları şifreleri açığa çıkarabilir.'
            ),
            L::t(
                'Deploy from release archives, or deny these paths in the web server (e.g. Apache: RedirectMatch 404 /\\.(git|env); nginx: location ~ /\\. { deny all; }).',
                'Sürüm arşivlerinden kurun ya da bu yolları web sunucusunda engelleyin (ör. Apache: RedirectMatch 404 /\\.(git|env); nginx: location ~ /\\. { deny all; }).'
            ),
            $found
        )];
    }
}
