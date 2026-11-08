<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

final class B03PasswordPolicy extends Check
{
    public function id(): string
    {
        return 'GG-B03';
    }

    public function name(): array
    {
        return L::t('Password policy for local accounts', 'Yerel hesaplar için parola politikası');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $local = (int) ($g->rows('SELECT COUNT(*) AS c FROM glpi_users WHERE authtype = 1 AND is_active = 1 AND is_deleted = 0')[0]['c'] ?? 0);
        if ($local === 0) {
            return []; // everything comes from LDAP/SSO
        }
        if ($g->config('use_password_security', '0') !== '1') {
            return [$this->finding(
                Finding::MEDIUM,
                L::t('Password security policy is disabled', 'Parola güvenlik politikası kapalı'),
                L::f(L::t('%d active local accounts may use any password, even one character long.', '%d etkin yerel hesap her türlü parolayı, tek karakterlik olanı bile kullanabilir.'), $local),
                L::t('Enable it in Setup > General > Security > Password security policy: minimum length 12, letters + digits (+ symbols).',
                     'Kurulum > Genel > Güvenlik > Parola güvenlik politikası bölümünden açın: en az 12 karakter, harf + rakam (+ sembol).'),
                ['use_password_security = 0']
            )];
        }
        $min = (int) $g->config('password_min_length', '8');
        if ($min < 10) {
            return [$this->finding(
                Finding::LOW,
                L::f(L::t('Minimum password length is only %d', 'En kısa parola uzunluğu yalnızca %d'), $min),
                L::t('Short passwords are cracked quickly if hashes leak and are easier to guess online.', 'Kısa parolalar, özetler sızarsa hızla kırılır ve tahmin edilmesi kolaydır.'),
                L::t('Raise the minimum length to at least 12.', 'En kısa uzunluğu en az 12 yapın.'),
                ['password_min_length = ' . $min]
            )];
        }
        return [];
    }
}
