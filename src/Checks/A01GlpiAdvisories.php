<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;
use GlpiGuard\SkipException;

final class A01GlpiAdvisories extends Check
{
    public function id(): string
    {
        return 'GG-A01';
    }

    public function name(): array
    {
        return L::t('GLPI version vs. published security advisories', 'GLPI sürümü ve yayımlanmış güvenlik duyuruları');
    }

    public function needsDb(): bool
    {
        return false;
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $v = $g->version();
        if ($v === '') {
            throw new SkipException('GLPI version not found');
        }
        $hits = $adv->forGlpi($v);
        if (!$hits) {
            return [];
        }
        $sev = Finding::INFO;
        $count = [];
        foreach ($hits as $a) {
            $s = isset(Finding::ORDER[$a['severity']]) ? $a['severity'] : Finding::MEDIUM;
            $count[$s] = ($count[$s] ?? 0) + 1;
            if (Finding::ORDER[$s] > Finding::ORDER[$sev]) {
                $sev = $s;
            }
        }
        $safe = Advisories::minimumSafe($hits, $v);
        $parts = ['en' => [], 'tr' => []];
        foreach ([Finding::CRITICAL, Finding::HIGH, Finding::MEDIUM, Finding::LOW] as $s) {
            if (!empty($count[$s])) {
                $parts['en'][] = $count[$s] . ' ' . strtolower(L::ui($s, 'en'));
                $parts['tr'][] = $count[$s] . ' ' . mb_strtolower(L::ui($s, 'tr'), 'UTF-8');
            }
        }
        $evidence = [];
        foreach (array_slice($hits, 0, 15) as $a) {
            $evidence[] = sprintf('[%s] %s%s — %s', strtoupper($a['severity']), $a['id'], $a['cve'] ? ' / ' . $a['cve'] : '', $a['summary']);
        }
        if (count($hits) > 15) {
            $evidence[] = '… +' . (count($hits) - 15);
        }
        return [$this->finding(
            $sev,
            L::f(L::t('GLPI %s is affected by %d published security advisories', 'GLPI %s, yayımlanmış %d güvenlik duyurusundan etkileniyor'), $v, count($hits)),
            [
                'en' => sprintf('Severity breakdown: %s. These vulnerabilities are public; several GLPI flaws (SQL injection, remote code execution) have been exploited in the wild.', implode(', ', $parts['en'])),
                'tr' => sprintf('Önem dağılımı: %s. Bu açıklar herkese açık; GLPI\'nin bazı açıkları (SQL injection, uzaktan kod çalıştırma) sahada istismar edildi.', implode(', ', $parts['tr'])),
            ],
            $safe !== null
                ? L::f(L::t(
                    'Upgrade GLPI to %s or later (latest release of your branch recommended). Back up files and database first, then follow the official upgrade procedure (php bin/console db:update).',
                    'GLPI\'yi en az %s sürümüne yükseltin (dalınızın en son sürümü önerilir). Önce dosya ve veritabanı yedeği alın, sonra resmi güncelleme adımlarını izleyin (php bin/console db:update).'
                ), $safe)
                : L::t(
                    'Upgrade to a maintained GLPI branch (see GG-A02). Back up files and database first.',
                    'Bakımı süren bir GLPI dalına yükseltin (bkz. GG-A02). Önce dosya ve veritabanı yedeği alın.'
                ),
            $evidence,
            array_values(array_unique(array_merge(['https://github.com/glpi-project/glpi/security/advisories'], array_map(static fn($a) => $a['url'], array_slice($hits, 0, 5)))))
        )];
    }
}
