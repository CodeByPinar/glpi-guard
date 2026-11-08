<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;
use GlpiGuard\Mo;

/**
 * Failed logins recorded in glpi_events during the last 30 days. GLPI stores the message already translated, so
 * patterns are built from the installation's own locales/*.mo.
 */
final class B06FailedLogins extends Check
{
    private const DAYS = 30;
    private const MEDIUM_AT = 100;
    private const HIGH_AT = 1000;

    public function id(): string
    {
        return 'GG-B06';
    }

    public function name(): array
    {
        return L::t('Failed login volume (brute-force signs)', 'Başarısız giriş yoğunluğu (kaba kuvvet belirtisi)');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $patterns = Mo::patterns($g->path('locales'), 'Failed login for %1$s from IP %2$s');
        $since = date('Y-m-d H:i:s', $g->now() - self::DAYS * 86400);
        $total = 0;
        $byIp = [];
        $byUser = [];
        foreach ($g->rows("SELECT message FROM glpi_events WHERE service = 'login' AND date >= ?", [$since]) as $e) {
            $msg = (string) $e['message'];
            foreach ($patterns as $p) {
                if (preg_match($p, $msg, $m)) {
                    $total++;
                    $user = trim($m[1] ?? '');
                    $ip = trim($m[2] ?? '');
                    $byIp[$ip] = ($byIp[$ip] ?? 0) + 1;
                    $byUser[$user] = ($byUser[$user] ?? 0) + 1;
                    break;
                }
            }
        }
        if ($total < self::MEDIUM_AT) {
            return [];
        }
        arsort($byIp);
        arsort($byUser);
        $top = static function (array $a): string {
            $s = [];
            foreach (array_slice($a, 0, 5, true) as $k => $n) {
                $s[] = ($k === '' ? '?' : $k) . ' ×' . $n;
            }
            return implode(', ', $s);
        };
        return [$this->finding(
            $total >= self::HIGH_AT ? Finding::HIGH : Finding::MEDIUM,
            L::f(L::t('%d failed logins in the last %d days', 'Son %2$d günde %1$d başarısız giriş'), $total, self::DAYS),
            L::t(
                'A high number of failures, especially from few IP addresses or against admin accounts, indicates password guessing.',
                'Özellikle az sayıda IP\'den ya da yönetici hesaplarına yönelik çok sayıda başarısız giriş, şifre tahmini girişimine işaret eder.'
            ),
            L::t(
                'Block the source IPs (firewall / fail2ban on GLPI\'s event log), do not expose GLPI login to the internet without VPN or SSO, enforce 2FA (GG-B04) and a password policy (GG-B03).',
                'Kaynak IP\'leri engelleyin (güvenlik duvarı / GLPI olay günlüğü için fail2ban), GLPI girişini VPN ya da SSO olmadan internete açmayın, iki adımlı doğrulamayı (GG-B04) ve parola politikasını (GG-B03) zorunlu yapın.'
            ),
            ['top IPs: ' . $top($byIp), 'top accounts: ' . $top($byUser)]
        )];
    }
}
