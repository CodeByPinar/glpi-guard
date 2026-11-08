<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/** Automatic actions: security-relevant housekeeping (session/log cleanup, password expiry, notifications) must run. */
final class F05Cron extends Check
{
    private const STALE_HOURS = 24;

    public function id(): string
    {
        return 'GG-F05';
    }

    public function name(): array
    {
        return L::t('Automatic actions are running', 'Otomatik eylemler çalışıyor mu');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $r = $g->rows('SELECT MAX(lastrun) AS last, SUM(mode = 1) AS internal, COUNT(*) AS total FROM glpi_crontasks WHERE state <> 0')[0] ?? [];
        $out = [];
        $last = $r['last'] ? strtotime((string) $r['last']) : false;
        if ($last === false || $last < $g->now() - self::STALE_HOURS * 3600) {
            $out[] = $this->finding(
                Finding::MEDIUM,
                L::t('Automatic actions have not run for more than a day', 'Otomatik eylemler bir günden uzun süredir çalışmamış'),
                L::t(
                    'Without them GLPI stops cleaning logs and temporary files, sending notifications (e.g. password expiry) and collecting mails.',
                    'Bunlar olmadan GLPI günlük ve geçici dosya temizliğini, bildirimleri (ör. şifre süresi) ve posta toplamayı durdurur.'
                ),
                L::t('Add the system cron line: * * * * * php /path/to/glpi/front/cron.php (GLPI 10) or php bin/console glpi:cron (GLPI 11+), as the web server user.',
                     'Sistem cron satırını ekleyin: * * * * * php /glpi/yolu/front/cron.php (GLPI 10) ya da php bin/console glpi:cron (GLPI 11+), web sunucusu kullanıcısıyla.'),
                ['last run: ' . ($r['last'] ?? 'never')]
            );
        }
        return $out;
    }
}
