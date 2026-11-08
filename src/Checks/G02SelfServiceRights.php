<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/** Self-service (simplified interface) profiles must not carry administration rights. */
final class G02SelfServiceRights extends Check
{
    private const ADMIN_RIGHTS = ['config', 'profile', 'user', 'group', 'entity', 'backup', 'logs', 'rule_ticket', 'rule_import', 'rule_ldap', 'rule_mailcollector', 'plugin', 'transfer', 'notification', 'authentication'];

    public function id(): string
    {
        return 'GG-G02';
    }

    public function name(): array
    {
        return L::t('Administration rights on self-service profiles', 'Self-servis profillerinde yönetim yetkisi');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $in = "'" . implode("','", self::ADMIN_RIGHTS) . "'";
        $bad = [];
        foreach ($g->rows("SELECT p.name AS profile, r.name, r.rights FROM glpi_profiles p JOIN glpi_profilerights r ON r.profiles_id = p.id
                           WHERE p.interface = 'helpdesk' AND r.name IN ($in) AND (r.rights & ?) <> 0 ORDER BY p.name, r.name", [Util::WRITE]) as $r) {
            $bad[] = $r['profile'] . ': ' . $r['name'] . ' (' . $r['rights'] . ')';
        }
        if (!$bad) {
            return [];
        }
        return [$this->finding(
            Finding::HIGH,
            L::t('Self-service profiles have administration write rights', 'Self-servis profillerinde yönetim yazma yetkisi var'),
            L::t('These profiles are given to every end user; administration rights there are almost always a mistake and an escalation path.',
                 'Bu profiller tüm son kullanıcılara verilir; buradaki yönetim yetkisi neredeyse her zaman hatadır ve yetki yükseltme yolu açar.'),
            L::t('Administration > Profiles > (self-service profile): remove write rights on the listed objects.',
                 'Yönetim > Profiller > (self-servis profili): listelenen nesnelerdeki yazma yetkilerini kaldırın.'),
            array_slice($bad, 0, 20)
        )];
    }
}
