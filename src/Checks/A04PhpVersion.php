<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;
use GlpiGuard\Paths;
use GlpiGuard\SkipException;

/** PHP end of security support (data/php.json, from php.net/supported-versions). Measures the PHP running this tool. */
final class A04PhpVersion extends Check
{
    public function id(): string
    {
        return 'GG-A04';
    }

    public function name(): array
    {
        return L::t('PHP version still supported', 'PHP sürümü hâlâ destekleniyor mu');
    }

    public function needsDb(): bool
    {
        return false;
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        if ($g->runtime() === 'docker') {
            throw new SkipException('running in a container: the PHP that serves GLPI cannot be measured (use the plugin or the phar on the server)');
        }
        $eol = json_decode((string) @file_get_contents(Paths::data('php.json')), true)['security_until'] ?? [];
        $branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $until = $eol[$branch] ?? null;
        if ($until === null) {
            return [];
        }
        $t = strtotime($until . ' 23:59:59');
        $note = $g->runtime() === 'web'
            ? L::t('Measured on the PHP version serving GLPI.', 'GLPI\'yi sunan PHP sürümü ölçüldü.')
            : L::t(
                'Measured on the PHP CLI running glpi-guard; make sure the web server uses the same version.',
                'glpi-guard\'ı çalıştıran PHP (CLI) ölçüldü; web sunucusunun da aynı sürümü kullandığından emin olun.'
            );
        if ($t < $g->now()) {
            return [$this->finding(
                Finding::HIGH,
                L::f(L::t('PHP %s no longer receives security fixes (since %s)', 'PHP %s artık güvenlik düzeltmesi almıyor (%s tarihinden beri)'), $branch, $until),
                $note,
                L::t('Upgrade PHP to a supported version compatible with your GLPI version.', 'PHP\'yi GLPI sürümünüzle uyumlu, desteklenen bir sürüme yükseltin.'),
                ['PHP ' . PHP_VERSION],
                ['https://www.php.net/supported-versions.php']
            )];
        }
        if ($t < $g->now() + 120 * 86400) {
            return [$this->finding(
                Finding::LOW,
                L::f(L::t('PHP %s security support ends on %s', 'PHP %s güvenlik desteği %s tarihinde bitiyor'), $branch, $until),
                $note,
                L::t('Plan the PHP upgrade now.', 'PHP yükseltmesini şimdiden planlayın.'),
                ['PHP ' . PHP_VERSION],
                ['https://www.php.net/supported-versions.php']
            )];
        }
        return [];
    }
}
