<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/** Outgoing SMTP and mail collectors without encryption / certificate validation. */
final class F04Mail extends Check
{
    private const SMTP_PLAIN = 1;     // MAIL_SMTP
    private const SMTP_SSL = 2;       // MAIL_SMTPSSL
    private const SMTP_TLS = 3;       // MAIL_SMTPTLS

    public function id(): string
    {
        return 'GG-F04';
    }

    public function name(): array
    {
        return L::t('Mail server connections are encrypted', 'E-posta sunucu bağlantıları şifreli mi');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $out = [];
        $mode = (int) $g->config('smtp_mode', '0');
        if ($g->config('use_notifications', '0') === '1' && $mode === self::SMTP_PLAIN && trim((string) $g->config('smtp_username', '')) !== '') {
            $out[] = $this->finding(
                Finding::MEDIUM,
                L::t('SMTP login is sent without encryption', 'SMTP girişi şifrelenmeden gönderiliyor'),
                L::t('The SMTP user name and password travel in clear text on every notification.', 'SMTP kullanıcı adı ve şifresi her bildirimde açık metin olarak gider.'),
                L::t('Setup > Notifications > Email followups configuration: use SMTP+TLS or SMTP+SSL.', 'Kurulum > Bildirimler > E-posta takip yapılandırması: SMTP+TLS ya da SMTP+SSL kullanın.'),
                ['smtp_mode = SMTP', 'host ' . $g->config('smtp_host', '')]
            );
        }
        if (in_array($mode, [self::SMTP_SSL, self::SMTP_TLS], true) && $g->config('smtp_check_certificate', '1') === '0') {
            $out[] = $this->finding(
                Finding::LOW,
                L::t('SMTP server certificate is not verified', 'SMTP sunucu sertifikası doğrulanmıyor'),
                L::t('Encryption without certificate validation does not stop a man-in-the-middle.', 'Sertifika doğrulanmadan yapılan şifreleme, araya giren saldırganı durdurmaz.'),
                L::t('Enable "Check certificate" and install the CA certificate on the server if it is internal.', '"Sertifikayı doğrula" seçeneğini açın; iç CA kullanılıyorsa CA sertifikasını sunucuya kurun.'),
                ['smtp_check_certificate = 0']
            );
        }
        $plain = [];
        $novalidate = [];
        foreach ($g->rows('SELECT name, host FROM glpi_mailcollectors WHERE is_active = 1') as $c) {
            $h = strtolower((string) $c['host']); // e.g. {mail.example.com:993/imap/ssl/novalidate-cert}
            if (strpos($h, '/ssl') === false && strpos($h, '/tls') === false) {
                $plain[] = $c['name'] . ' ' . $c['host'];
            } elseif (strpos($h, '/novalidate-cert') !== false) {
                $novalidate[] = $c['name'] . ' ' . $c['host'];
            }
        }
        if ($plain) {
            $out[] = $this->finding(
                Finding::MEDIUM,
                L::f(L::t('%d mail collectors connect without SSL/TLS', '%d posta toplayıcı SSL/TLS olmadan bağlanıyor'), count($plain)),
                L::t('The mailbox password and the collected mails travel in clear text.', 'Posta kutusu şifresi ve toplanan e-postalar açık metin olarak gider.'),
                L::t('Setup > Receivers: use IMAP/POP with SSL (port 993/995) or TLS.', 'Kurulum > Alıcılar: SSL (993/995 portu) ya da TLS ile IMAP/POP kullanın.'),
                $plain
            );
        }
        if ($novalidate) {
            $out[] = $this->finding(
                Finding::LOW,
                L::f(L::t('%d mail collectors skip certificate validation', '%d posta toplayıcı sertifika doğrulamasını atlıyor'), count($novalidate)),
                L::t('"novalidate-cert" accepts any certificate, so the connection can be intercepted.', '"novalidate-cert" her sertifikayı kabul eder; bağlantı araya girilerek dinlenebilir.'),
                L::t('Remove "No validation of certificate" and trust the server certificate properly.', '"Sertifika doğrulaması yok" seçeneğini kaldırın, sunucu sertifikasını düzgünce güvenilir kılın.'),
                $novalidate
            );
        }
        return $out;
    }
}
