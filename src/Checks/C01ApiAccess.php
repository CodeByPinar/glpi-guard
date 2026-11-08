<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/** Legacy REST API (apirest.php): login with credentials and API clients without IP restriction / app token. */
final class C01ApiAccess extends Check
{
    public function id(): string
    {
        return 'GG-C01';
    }

    public function name(): array
    {
        return L::t('REST API exposure', 'REST API açıklığı');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        if ($g->config('enable_api', '0') !== '1') {
            return [];
        }
        $out = [];
        $open = [];
        $noToken = [];
        foreach ($g->rows('SELECT name, ipv4_range_start, ipv4_range_end, ipv6, app_token FROM glpi_apiclients WHERE is_active = 1') as $c) {
            $noIp = ($c['ipv4_range_start'] === null || $c['ipv4_range_start'] === '' || (int) $c['ipv4_range_start'] === 0 && (string) $c['ipv4_range_end'] === '4294967295')
                && trim((string) $c['ipv6']) === '';
            $tok = trim((string) $c['app_token']) !== '';
            if ($noIp && !$tok) {
                $open[] = (string) $c['name'];
            } elseif (!$tok) {
                $noToken[] = (string) $c['name'];
            }
        }
        if ($open) {
            $out[] = $this->finding(
                $g->config('enable_api_login_credentials', '0') === '1' ? Finding::HIGH : Finding::MEDIUM,
                L::t('API client open to every IP address without an application token', 'API istemcisi her IP\'ye açık ve uygulama belirteci yok'),
                L::t(
                    'Any host that can reach GLPI can call the REST API through this client; only the user credentials/token protect it.',
                    'GLPI\'ye erişebilen her makine bu istemci üzerinden REST API\'yi çağırabilir; tek koruma kullanıcı şifresi/belirtecidir.'
                ),
                L::t(
                    'Setup > General > API > API clients: restrict the IPv4/IPv6 range to the systems that really use the API and set an application token. Disable unused clients.',
                    'Kurulum > Genel > API > API istemcileri: IPv4/IPv6 aralığını API\'yi gerçekten kullanan sistemlerle sınırlayın ve uygulama belirteci tanımlayın. Kullanılmayan istemcileri kapatın.'
                ),
                [Util::names($open)]
            );
        }
        if ($noToken) {
            $out[] = $this->finding(
                Finding::LOW,
                L::t('API client without an application token', 'Uygulama belirteci olmayan API istemcisi'),
                L::t('IP restriction exists, but an application token adds a second barrier.', 'IP kısıtı var, ama uygulama belirteci ikinci bir engel sağlar.'),
                L::t('Generate an application token for the client.', 'İstemci için bir uygulama belirteci oluşturun.'),
                [Util::names($noToken)]
            );
        }
        if ($g->config('enable_api_login_credentials', '0') === '1') {
            $out[] = $this->finding(
                Finding::MEDIUM,
                L::t('API accepts login with user name and password', 'API kullanıcı adı ve şifreyle girişi kabul ediyor'),
                L::t(
                    'Passwords are sent to the API on every session and can be brute-forced there; two-factor authentication does not apply to the API.',
                    'Şifre her oturumda API\'ye gönderilir ve orada tahmin denemesine açıktır; iki adımlı doğrulama API\'de uygulanmaz.'
                ),
                L::t(
                    'Setup > General > API: disable "Enable login with credentials" and use personal API tokens (or OAuth on GLPI 11+).',
                    'Kurulum > Genel > API: "Kimlik bilgileriyle girişi etkinleştir" seçeneğini kapatın; kişisel API belirteçleri (GLPI 11+ için OAuth) kullanın.'
                ),
                ['enable_api_login_credentials = 1']
            );
        }
        return $out;
    }
}
