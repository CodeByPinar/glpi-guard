<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;
use GlpiGuard\SkipException;

/**
 * A branch counts as maintained when the GLPI team published a security fix for it during the last 12 months
 * (derived from the advisory data — no hand-maintained end-of-life table to go stale).
 */
final class A02GlpiBranch extends Check
{
    public function id(): string
    {
        return 'GG-A02';
    }

    public function name(): array
    {
        return L::t('GLPI branch still receives security fixes', 'GLPI dalı hâlâ güvenlik düzeltmesi alıyor mu');
    }

    public function needsDb(): bool
    {
        return false;
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $v = $g->version();
        if ($v === '' || $adv->generated() === '') {
            throw new SkipException('version or advisory data missing');
        }
        $maintained = $adv->maintainedBranches($g->now());
        if (!$maintained) {
            throw new SkipException('no recent advisory data');
        }
        $branch = $g->branch();
        if (in_array($branch, $maintained, true)) {
            return [];
        }
        if (version_compare($branch, end($maintained), '>')) {
            return []; // newer than anything patched recently (e.g. brand-new major)
        }
        return [$this->finding(
            Finding::HIGH,
            L::f(L::t('GLPI %s branch no longer receives security fixes', 'GLPI %s dalı artık güvenlik düzeltmesi almıyor'), $branch),
            L::f(L::t(
                'No security fix was published for the %s branch during the last 12 months, while branches %s were fixed. New vulnerabilities in your version will stay open.',
                'Son 12 ayda %s dalı için güvenlik düzeltmesi yayımlanmadı; bu sürede %s dalları düzeltildi. Sürümünüzde çıkacak yeni açıklar kapanmayacak.'
            ), $branch, implode(', ', $maintained)),
            L::f(L::t(
                'Plan a migration to GLPI %s (check plugin compatibility first, test on a copy).',
                'GLPI %s sürümüne geçişi planlayın (önce eklenti uyumluluğunu kontrol edin, bir kopyada deneyin).'
            ), end($maintained)),
            ['GLPI ' . $v],
            ['https://glpi-project.org/downloads/']
        )];
    }
}
