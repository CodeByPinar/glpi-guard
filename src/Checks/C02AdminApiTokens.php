<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

final class C02AdminApiTokens extends Check
{
    public function id(): string
    {
        return 'GG-C02';
    }

    public function name(): array
    {
        return L::t('Personal API tokens on administrator accounts', 'Yönetici hesaplarında kişisel API belirteci');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $profiles = Util::adminProfiles($g);
        $users = array_filter(Util::usersWithProfiles($g, array_keys($profiles), 'u.api_token, u.api_token_date'), static fn($u) => trim((string) $u['api_token']) !== '');
        if (!$users) {
            return [];
        }
        $ev = [];
        foreach ($users as $u) {
            $ev[] = $u['name'] . ' (' . ($u['api_token_date'] ? 'since ' . substr((string) $u['api_token_date'], 0, 10) : 'date unknown') . ')';
        }
        return [$this->finding(
            $g->config('enable_api', '0') === '1' ? Finding::MEDIUM : Finding::LOW,
            L::f(L::t('%d super-admin accounts have a personal API token', '%d süper yönetici hesabında kişisel API belirteci var'), count($users)),
            L::t(
                'The token never expires and gives the full rights of the account without password or second factor. Tokens end up in scripts and configuration files.',
                'Belirteç süresiz geçerlidir ve şifre ya da ikinci doğrulama olmadan hesabın tüm yetkisini verir. Belirteçler betiklere ve ayar dosyalarına girer.'
            ),
            L::t(
                'Use a dedicated API user with a minimal profile for integrations; regenerate or delete tokens of personal admin accounts (My settings > Remote access keys).',
                'Entegrasyonlar için en az yetkili ayrı bir API kullanıcısı kullanın; kişisel yönetici hesaplarının belirteçlerini yenileyin ya da silin (Ayarlarım > Uzaktan erişim anahtarları).'
            ),
            [Util::names($ev)]
        )];
    }
}
