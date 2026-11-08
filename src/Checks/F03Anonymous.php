<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

final class F03Anonymous extends Check
{
    public function id(): string
    {
        return 'GG-F03';
    }

    public function name(): array
    {
        return L::t('Anonymous access', 'Anonim erişim');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $out = [];
        if ($g->config('use_anonymous_helpdesk', '0') === '1') {
            $out[] = $this->finding(
                Finding::MEDIUM,
                L::t('Anonymous ticket creation is allowed', 'Anonim kayıt açmaya izin veriliyor'),
                L::t('Anyone who reaches GLPI can create tickets without logging in: spam, phishing content sent to technicians, extra attack surface.',
                     'GLPI\'ye erişebilen herkes giriş yapmadan kayıt açabilir: istenmeyen kayıt, teknisyenlere oltalama içeriği, ek saldırı yüzeyi.'),
                L::t('Setup > General > Assistance: disable "Allow anonymous ticket creation" unless GLPI is only reachable internally and you really need it.',
                     'Kurulum > Genel > Destek: GLPI yalnız iç ağdan erişilebilir değilse ve gerçekten gerekmiyorsa "Anonim kayıt açmaya izin ver" seçeneğini kapatın.'),
                ['use_anonymous_helpdesk = 1']
            );
        }
        if ($g->config('use_anonymous_followups', '0') === '1') {
            $out[] = $this->finding(
                Finding::LOW,
                L::t('Anonymous follow-ups by e-mail are allowed', 'E-postayla anonim takip eklenmesine izin veriliyor'),
                L::t('Mails from unknown senders are added to tickets.', 'Bilinmeyen gönderenlerden gelen e-postalar kayıtlara eklenir.'),
                L::t('Setup > General > Assistance: disable anonymous follow-ups if not needed.', 'Kurulum > Genel > Destek: gerekmiyorsa anonim takipleri kapatın.'),
                ['use_anonymous_followups = 1']
            );
        }
        return $out;
    }
}
