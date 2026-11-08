<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/**
 * Default accounts created by the GLPI installer. The password is checked LOCALLY against the stored hash
 * (password_verify / legacy md5) — never by trying to log in.
 */
final class B01DefaultAccounts extends Check
{
    /** login => [default password, severity if still default] */
    private const DEFAULTS = [
        'glpi'      => ['glpi', Finding::CRITICAL],
        'tech'      => ['tech', Finding::HIGH],
        'normal'    => ['normal', Finding::HIGH],
        'post-only' => ['postonly', Finding::MEDIUM],
    ];

    public function id(): string
    {
        return 'GG-B01';
    }

    public function name(): array
    {
        return L::t('Default accounts with default passwords', 'Varsayılan şifreli varsayılan hesaplar');
    }

    public static function isDefaultPassword(string $hash, string $password): bool
    {
        if ($hash === '') {
            return false;
        }
        if (preg_match('~^[a-f0-9]{32}$~i', $hash)) { // very old GLPI (md5)
            return hash_equals(strtolower($hash), md5($password));
        }
        if (preg_match('~^[a-f0-9]{40}$~i', $hash)) { // legacy sha1
            return hash_equals(strtolower($hash), sha1($password));
        }
        return password_verify($password, $hash);
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $rows = $g->rows("SELECT name, password, authtype FROM glpi_users WHERE name IN ('glpi','tech','normal','post-only') AND is_active = 1 AND is_deleted = 0");
        $out = [];
        foreach ($rows as $u) {
            $login = (string) $u['name'];
            [$pass, $sev] = self::DEFAULTS[$login];
            if ((int) $u['authtype'] !== 1 || !self::isDefaultPassword((string) $u['password'], $pass)) {
                continue;
            }
            $out[] = $this->finding(
                $sev,
                L::f(L::t('Default account "%s" is active with its default password', 'Varsayılan "%s" hesabı varsayılan şifresiyle etkin'), $login),
                $login === 'glpi'
                    ? L::t('"glpi" is a super-admin account. Anyone who knows the public default password can take over the whole GLPI.',
                           '"glpi" süper yönetici hesabıdır. Herkesçe bilinen varsayılan şifreyi bilen herkes GLPI\'nin tamamını ele geçirebilir.')
                    : L::t('The default password of this account is publicly documented.', 'Bu hesabın varsayılan şifresi herkese açık belgelerde yazıyor.'),
                L::f(L::t(
                    'Administration > Users > %s: change the password, or disable/delete the account if it is not needed.',
                    'Yönetim > Kullanıcılar > %s: şifresini değiştirin; gerekmiyorsa hesabı devre dışı bırakın ya da silin.'
                ), $login),
                [$login . ' (password = default, verified locally)'],
                ['https://glpi-install.readthedocs.io/en/latest/install/wizard.html#end-of-installation']
            );
        }
        return $out;
    }
}
