<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

final class A05SchemaVersion extends Check
{
    public function id(): string
    {
        return 'GG-A05';
    }

    public function name(): array
    {
        return L::t('Database matches the installed GLPI code', 'Veritabanı kurulu GLPI koduyla uyumlu mu');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $code = $g->version();
        $db = (string) $g->config('version', '');
        if ($code === '' || $db === '' || version_compare($code, $db, '==')) {
            return [];
        }
        return [$this->finding(
            Finding::MEDIUM,
            L::f(L::t('Code is GLPI %s but the database is still %s', 'Kod GLPI %s ama veritabanı hâlâ %s'), $code, $db),
            L::t(
                'An upgrade was copied but not finished (or a rollback left mixed versions). GLPI may run in maintenance-like state and security fixes of the new version may not be fully applied.',
                'Güncelleme dosyaları kopyalanmış ama işlem tamamlanmamış (ya da geri dönüş karışık sürüm bırakmış). Yeni sürümün güvenlik düzeltmeleri tam uygulanmamış olabilir.'
            ),
            L::t('Run "php bin/console db:update" (after a backup) or restore the matching code version.', 'Yedek aldıktan sonra "php bin/console db:update" çalıştırın ya da veritabanına uyan kod sürümüne dönün.'),
            ['code ' . $code, 'database ' . $db]
        )];
    }
}
