<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

/** Uploadable document types that allow server-side code or active content (stored XSS). */
final class F02DocumentTypes extends Check
{
    private const CODE = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp'];
    private const ACTIVE = ['html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'js', 'mjs', 'xml', 'xsl', 'swf', 'hta'];

    public function id(): string
    {
        return 'GG-F02';
    }

    public function name(): array
    {
        return L::t('Dangerous uploadable document types', 'Yüklenebilir tehlikeli doküman türleri');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $code = [];
        $active = [];
        foreach ($g->rows('SELECT name, ext FROM glpi_documenttypes WHERE is_uploadable = 1') as $t) {
            $ext = strtolower(trim((string) $t['ext']));
            if (in_array($ext, self::CODE, true)) {
                $code[] = '.' . $ext;
            } elseif (in_array($ext, self::ACTIVE, true)) {
                $active[] = '.' . $ext;
            }
        }
        $out = [];
        $where = L::t('Setup > Dropdowns > Document types: set "Authorized upload" to No for these extensions.',
                      'Kurulum > Açılır listeler > Doküman türleri: bu uzantılarda "Yüklemeye izin ver" seçeneğini Hayır yapın.');
        if ($code) {
            $out[] = $this->finding(
                Finding::CRITICAL,
                L::t('Users may upload server-side script files', 'Kullanıcılar sunucuda çalışabilecek betik dosyaları yükleyebilir'),
                L::t('Combined with any path/file vulnerability, an uploaded script becomes remote code execution.', 'Herhangi bir dosya/yol açığıyla birleştiğinde yüklenen betik uzaktan kod çalıştırmaya dönüşür.'),
                $where,
                [implode(' ', $code)]
            );
        }
        if ($active) {
            $out[] = $this->finding(
                Finding::LOW,
                L::t('Users may upload active content (HTML, SVG, JS…)', 'Kullanıcılar etkin içerik (HTML, SVG, JS…) yükleyebilir'),
                L::t('GLPI serves documents as downloads, but such files can still carry scripts that run when opened locally or in another viewer (phishing, XSS). Some are allowed by default.',
                     'GLPI dokümanları indirme olarak sunar; yine de bu dosyalar yerelde ya da başka bir görüntüleyicide açıldığında çalışan betik taşıyabilir (oltalama, XSS). Bir kısmına varsayılan olarak izin verilir.'),
                $where,
                [implode(' ', $active)]
            );
        }
        return $out;
    }
}
