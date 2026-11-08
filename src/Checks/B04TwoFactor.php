<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;
use GlpiGuard\SkipException;

/** Built-in TOTP two-factor authentication (GLPI 11+): is it enforced for accounts that can change the configuration? */
final class B04TwoFactor extends Check
{
    public function id(): string
    {
        return 'GG-B04';
    }

    public function name(): array
    {
        return L::t('Two-factor authentication for administrators', 'Yöneticiler için iki adımlı doğrulama');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        if (!$g->columnExists('glpi_profiles', '2fa_enforced') || !$g->columnExists('glpi_users', '2fa')) {
            throw new SkipException('this GLPI version has no built-in 2FA');
        }
        if ($g->config('2fa_enforced', '0') === '1') {
            return [];
        }
        $profiles = Util::adminProfiles($g);
        if (!$profiles) {
            return [];
        }
        $in = implode(',', array_map('intval', array_keys($profiles)));
        $notEnforced = [];
        foreach ($g->rows("SELECT id, name FROM glpi_profiles WHERE id IN ($in) AND `2fa_enforced` = 0") as $p) {
            $notEnforced[(int) $p['id']] = (string) $p['name'];
        }
        if (!$notEnforced) {
            return [];
        }
        // local accounts only: LDAP/SSO users may have MFA at the identity provider
        $users = array_filter(Util::usersWithProfiles($g, array_keys($notEnforced), 'u.authtype, u.`2fa` AS tfa'), static function ($u) {
            return (int) $u['authtype'] === 1 && trim((string) $u['tfa']) === '';
        });
        if (!$users) {
            return [];
        }
        return [$this->finding(
            Finding::MEDIUM,
            L::f(L::t('%d local administrator accounts log in without a second factor', '%d yerel yönetici hesabı ikinci doğrulama olmadan giriş yapıyor'), count($users)),
            L::f(L::t(
                'Two-factor authentication is not enforced for profile(s) %s. A stolen password alone gives full control.',
                '%s profil(ler)i için iki adımlı doğrulama zorunlu değil. Çalınan tek bir şifre tam yetki sağlar.'
            ), implode(', ', $notEnforced)),
            L::t(
                'Administration > Profiles > (profile) > enforce two-factor authentication, or enforce it globally in Setup > General > Security.',
                'Yönetim > Profiller > (profil) > iki adımlı doğrulamayı zorunlu yapın ya da Kurulum > Genel > Güvenlik bölümünden herkes için açın.'
            ),
            [Util::names(array_column($users, 'name'))]
        )];
    }
}
