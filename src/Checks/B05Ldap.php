<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

final class B05Ldap extends Check
{
    public function id(): string
    {
        return 'GG-B05';
    }

    public function name(): array
    {
        return L::t('LDAP directories use encryption', 'LDAP dizinleri şifreli bağlantı kullanıyor mu');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $plain = [];
        foreach ($g->rows('SELECT name, host, port, use_tls FROM glpi_authldaps WHERE is_active = 1') as $l) {
            $host = strtolower(trim((string) $l['host']));
            $secure = (int) $l['use_tls'] === 1 || strpos($host, 'ldaps://') === 0 || (int) $l['port'] === 636;
            if (!$secure) {
                $plain[] = $l['name'] . ' (' . $l['host'] . ':' . $l['port'] . ')';
            }
        }
        if (!$plain) {
            return [];
        }
        return [$this->finding(
            Finding::HIGH,
            L::f(L::t('%d LDAP directories are used without TLS', '%d LDAP dizini TLS olmadan kullanılıyor'), count($plain)),
            L::t(
                'Every LDAP login sends the user\'s password in clear text over the network; the bind account password too.',
                'Her LDAP girişinde kullanıcının şifresi ağda açık metin olarak gider; bağlantı hesabının şifresi de.'
            ),
            L::t(
                'Setup > Authentication > LDAP directories > (directory): enable "Use TLS" (StartTLS) or use ldaps:// on port 636, and make sure the server certificate is trusted.',
                'Kurulum > Kimlik doğrulama > LDAP dizinleri > (dizin): "TLS kullan" (StartTLS) seçeneğini açın ya da 636 portunda ldaps:// kullanın; sunucu sertifikasının güvenilir olduğundan emin olun.'
            ),
            $plain
        )];
    }
}
