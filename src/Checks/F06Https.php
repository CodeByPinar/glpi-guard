<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/** The URL GLPI uses for itself (links in notifications) — a strong hint whether HTTPS is used. */
final class F06Https extends Check
{
    public function id(): string
    {
        return 'GG-F06';
    }

    public function name(): array
    {
        return L::t('GLPI is served over HTTPS', 'GLPI HTTPS üzerinden sunuluyor mu');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $url = trim((string) $g->config('url_base', ''));
        if ($url === '' || stripos($url, 'https://') === 0) {
            return [];
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return [$this->finding(
                Finding::INFO,
                L::t('The GLPI URL is set to localhost', 'GLPI adresi localhost olarak ayarlı'),
                L::t('Links in notifications will not work for users and HTTPS cannot be judged.', 'Bildirimlerdeki bağlantılar kullanıcılar için çalışmaz ve HTTPS değerlendirilemez.'),
                L::t('Setup > General > General setup: set "URL of the application" to the real https:// address.', 'Kurulum > Genel > Genel ayarlar: "Uygulamanın adresi" alanına gerçek https:// adresini yazın.'),
                ['url_base = ' . $url]
            )];
        }
        return [$this->finding(
            Finding::HIGH,
            L::t('GLPI is configured with an http:// address', 'GLPI http:// adresiyle yapılandırılmış'),
            L::t('Passwords, session cookies and ticket contents travel in clear text and can be read or stolen on the network.',
                 'Şifreler, oturum çerezleri ve kayıt içerikleri açık metin gider; ağda okunabilir ya da çalınabilir.'),
            L::t('Install a certificate, redirect HTTP to HTTPS, then update Setup > General > "URL of the application" to https://.',
                 'Sertifika kurun, HTTP\'yi HTTPS\'e yönlendirin, sonra Kurulum > Genel > "Uygulamanın adresi" alanını https:// yapın.'),
            ['url_base = ' . $url]
        )];
    }
}
