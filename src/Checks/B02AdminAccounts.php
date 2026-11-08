<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

final class B02AdminAccounts extends Check
{
    private const MAX_ADMINS = 5;
    private const STALE_DAYS = 180;

    public function id(): string
    {
        return 'GG-B02';
    }

    public function name(): array
    {
        return L::t('Number and activity of super-admin accounts', 'Süper yönetici hesaplarının sayısı ve kullanımı');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $profiles = Util::adminProfiles($g);
        $users = Util::usersWithProfiles($g, array_keys($profiles));
        $out = [];
        if (count($users) > self::MAX_ADMINS) {
            $out[] = $this->finding(
                Finding::MEDIUM,
                L::f(L::t('%d active accounts can change the GLPI configuration', '%d etkin hesap GLPI yapılandırmasını değiştirebiliyor'), count($users)),
                L::f(L::t(
                    'Profiles with configuration write right: %s. Every such account is a full takeover path if its password leaks.',
                    'Yapılandırma yazma yetkili profiller: %s. Bu hesaplardan birinin şifresi sızarsa GLPI\'nin tamamı ele geçirilebilir.'
                ), implode(', ', $profiles)),
                L::t('Keep super-admin rights to a few named people; give others a narrower profile.', 'Süper yönetici yetkisini birkaç kişiyle sınırlayın; diğerlerine daha dar bir profil verin.'),
                [Util::names(array_column($users, 'name'))]
            );
        }
        $stale = [];
        $limit = $g->now() - self::STALE_DAYS * 86400;
        foreach ($users as $u) {
            $t = $u['last_login'] ? strtotime((string) $u['last_login']) : false;
            if ($t === false || $t < $limit) {
                $stale[] = $u['name'] . ' (' . ($u['last_login'] ? substr((string) $u['last_login'], 0, 10) : 'never') . ')';
            }
        }
        if ($stale) {
            $out[] = $this->finding(
                Finding::LOW,
                L::f(L::t('%d super-admin accounts unused for %d+ days', '%d süper yönetici hesabı %d+ gündür kullanılmıyor'), count($stale), self::STALE_DAYS),
                L::t('Dormant privileged accounts are a common entry point (forgotten passwords, former staff).', 'Kullanılmayan yetkili hesaplar sık kullanılan bir giriş noktasıdır (unutulan şifreler, ayrılan çalışanlar).'),
                L::t('Disable these accounts or remove their super-admin profile.', 'Bu hesapları devre dışı bırakın ya da süper yönetici profilini kaldırın.'),
                [Util::names($stale)]
            );
        }
        return $out;
    }
}
