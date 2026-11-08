<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/**
 * A profile that may edit profiles but not the configuration can grant itself any right (escalation path).
 * Several "full configuration" profiles are reported for review.
 */
final class G01PrivilegeEscalation extends Check
{
    public function id(): string
    {
        return 'GG-G01';
    }

    public function name(): array
    {
        return L::t('Profiles that can escalate their own rights', 'Kendi yetkisini yükseltebilen profiller');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $rights = [];
        foreach ($g->rows("SELECT p.id, p.name, r.name AS r, r.rights FROM glpi_profiles p JOIN glpi_profilerights r ON r.profiles_id = p.id WHERE r.name IN ('config','profile')") as $x) {
            $rights[(int) $x['id']]['name'] = (string) $x['name'];
            $rights[(int) $x['id']][(string) $x['r']] = (int) $x['rights'];
        }
        $escalate = [];
        $full = [];
        foreach ($rights as $id => $p) {
            $cfgW = (($p['config'] ?? 0) & Util::UPDATE) !== 0;
            $profW = (($p['profile'] ?? 0) & (Util::UPDATE | Util::CREATE)) !== 0;
            if ($profW && !$cfgW) {
                $escalate[] = $p['name'];
            }
            if ($cfgW) {
                $full[] = $p['name'];
            }
        }
        $out = [];
        if ($escalate) {
            $users = 0;
            foreach ($rights as $id => $p) {
                if (in_array($p['name'], $escalate, true)) {
                    $users += count(Util::usersWithProfiles($g, [$id]));
                }
            }
            $out[] = $this->finding(
                Finding::HIGH,
                L::f(L::t('%d profiles can edit profiles without being super-admin', '%d profil, süper yönetici olmadan profilleri düzenleyebiliyor'), count($escalate)),
                L::f(L::t(
                    'Editing profiles lets a user give their own profile every right, including configuration. %d active users hold these profiles.',
                    'Profil düzenleyebilen kullanıcı kendi profiline yapılandırma dahil her yetkiyi verebilir. Bu profillerde %d etkin kullanıcı var.'
                ), $users),
                L::t('Administration > Profiles > (profile) > Administration tab: remove Update/Create on "Profiles" unless the profile is meant to be super-admin.',
                     'Yönetim > Profiller > (profil) > Yönetim sekmesi: profil süper yönetici olmayacaksa "Profiller" için Güncelleme/Oluşturma yetkisini kaldırın.'),
                [Util::names($escalate)]
            );
        }
        if (count($full) > 1) {
            $out[] = $this->finding(
                Finding::LOW,
                L::f(L::t('%d profiles can change the general configuration', '%d profil genel yapılandırmayı değiştirebiliyor'), count($full)),
                L::t('More than one full-admin profile makes rights harder to review.', 'Birden fazla tam yetkili profil, yetkilerin gözden geçirilmesini zorlaştırır.'),
                L::t('Keep a single super-admin profile and review the others.', 'Tek bir süper yönetici profili bırakın, diğerlerini gözden geçirin.'),
                [Util::names($full)]
            );
        }
        return $out;
    }
}
