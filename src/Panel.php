<?php

declare(strict_types=1);

namespace GlpiPlugin\Glpiguard;

use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\L;
use GlpiGuard\Result;
use Html;

/** The plugin page inside GLPI (Tabler/Bootstrap markup, works in GLPI 10, 11, 12; light and dark themes). */
final class Panel
{
    private const COLOR = [
        Finding::CRITICAL => ['var(--tblr-red, #d63939)', 'bg-red text-white'],
        Finding::HIGH     => ['var(--tblr-orange, #f76707)', 'bg-orange text-white'],
        Finding::MEDIUM   => ['var(--tblr-yellow, #f59f00)', 'bg-yellow text-dark'],
        Finding::LOW      => ['var(--tblr-azure, #4299e1)', 'bg-azure text-white'],
        Finding::INFO     => ['var(--tblr-secondary, #667382)', 'bg-secondary text-white'],
    ];
    private const GRADE = ['A' => 'bg-green', 'B' => 'bg-lime', 'C' => 'bg-yellow', 'D' => 'bg-orange', 'F' => 'bg-red'];

    private static function e($s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function t(string $en, string $tr, string $lang): string
    {
        return $lang === 'tr' ? $tr : $en;
    }

    /** @param array{row:array,result:Result}|null $scan */
    public static function render(?array $scan, array $history, string $lang): string
    {
        $e = [self::class, 'e'];
        $form = '<form method="post" action="' . $e(Menu::url('front/index.php')) . '" class="d-inline">'
            . '<button type="submit" name="scan" value="1" class="btn btn-primary"><i class="ti ti-player-play me-1"></i>'
            . $e(self::t('Scan now', 'Şimdi tara', $lang)) . '</button>' . self::closeForm();
        $intro = '<p class="text-muted mb-0">' . $e(self::t(
            'Read-only audit of this GLPI: known vulnerabilities, default accounts, risky settings, exposed files. Nothing is changed, no exploit or login attempt is made. Runs weekly as an automatic action too.',
            'Bu GLPI\'nin salt okunur denetimi: bilinen açıklar, varsayılan hesaplar, riskli ayarlar, açıkta kalan dosyalar. Hiçbir şey değiştirilmez, açık ya da giriş denenmez. Otomatik eylem olarak haftada bir de çalışır.',
            $lang
        )) . '</p>';
        // own lang attribute: uppercase headings must follow the report language, not GLPI's (Turkish dotted İ)
        $out = '<div class="container-fluid gg-wrap" lang="' . $lang . '" style="max-width:1200px">' . self::css();
        if ($scan === null) {
            return $out . '<div class="card"><div class="card-body text-center py-5"><i class="ti ti-shield-check" style="font-size:48px"></i>'
                . '<h2 class="mt-2">GLPI Guard</h2>' . $intro . '<div class="mt-4">' . $form . '</div></div></div></div>';
        }
        $r = $scan['result'];
        $row = $scan['row'];
        $grade = $r->grade();
        $counts = '';
        foreach ($r->counts() as $sev => $n) {
            $counts .= '<span class="badge ' . self::COLOR[$sev][1] . ' me-1">' . (int) $n . ' ' . $e(L::ui($sev, $lang)) . '</span>';
        }
        $dl = static fn(string $fmt, string $label) => '<a class="btn btn-outline-secondary" href="' . $e(Menu::url('front/report.php', ['id' => $row['id'], 'format' => $fmt, 'lang' => $lang])) . '"><i class="ti ti-download me-1"></i>' . $e($label) . '</a>';
        $m = $r->meta;
        $out .= '<div class="card mb-3"><div class="card-body"><div class="d-flex flex-wrap gap-3 align-items-center">'
            . '<div class="gg-grade ' . (self::GRADE[$grade] ?? 'bg-secondary') . '">' . $e($grade) . '</div>'
            . '<div class="flex-grow-1"><div class="h2 mb-1">' . $e(self::t('Security score', 'Güvenlik puanı', $lang)) . ': ' . $r->score() . '/100</div>'
            . '<div class="mb-2">' . $counts . '</div>'
            . '<div class="text-muted small">' . $e(self::t('Scan', 'Tarama', $lang)) . ' #' . (int) $row['id'] . ' · ' . $e($row['date_creation']) . ' · '
            . $e($row['source'] === 'cron' ? self::t('automatic', 'otomatik', $lang) : self::t('manual', 'elle', $lang)) . ' · GLPI ' . $e($m['glpi'] ?? '')
            . ' · PHP ' . $e($m['php'] ?? '') . ' · ' . $e(L::ui('advisorydb', $lang)) . ' ' . $e($m['advisory_db'] ?? '-') . ' (' . (int) ($m['advisories'] ?? 0) . ')</div></div>'
            . '<div class="d-flex flex-wrap gap-2">' . $form . $dl('html', 'HTML') . $dl('json', 'JSON') . '</div>'
            . '</div><div class="mt-3">' . $intro . '</div></div></div>';

        // findings
        $out .= '<h3 class="mt-4 mb-2">' . $e(L::ui('findings', $lang)) . ' (' . count($r->findings) . ')</h3>';
        if (!$r->findings) {
            $out .= '<div class="alert alert-success">' . $e(L::ui('none', $lang)) . '</div>';
        }
        foreach ($r->findings as $f) {
            $a = $f->toArray($lang);
            $col = self::COLOR[$f->severity];
            $ev = $a['evidence'] ? '<details class="mt-2"' . (count($a['evidence']) <= 3 ? ' open' : '') . '><summary class="text-muted small">' . $e(L::ui('evidence', $lang)) . ' (' . count($a['evidence']) . ')</summary><ul class="gg-ev mb-0">'
                . implode('', array_map(static fn($x) => '<li>' . self::e($x) . '</li>', $a['evidence'])) . '</ul></details>' : '';
            $refs = $a['refs'] ? '<div class="small text-muted mt-2">' . $e(L::ui('refs', $lang)) . ': ' . implode(' · ', array_map(static fn($u) => '<a href="' . self::e($u) . '" target="_blank" rel="noopener noreferrer">' . self::e(preg_replace('~^https?://(www\.)?~', '', $u)) . '</a>', $a['refs'])) . '</div>' : '';
            $out .= '<div class="card mb-2 gg-f" style="border-left:4px solid ' . $col[0] . '"><div class="card-body py-3">'
                . '<div class="d-flex flex-wrap gap-2 align-items-baseline"><span class="badge ' . $col[1] . '">' . $e(L::ui($f->severity, $lang)) . '</span>'
                . '<strong class="flex-grow-1">' . $e($a['title']) . '</strong><code class="text-muted">' . $e($a['check']) . '</code></div>'
                . '<div class="mt-2">' . $e($a['detail']) . '</div>' . $ev
                . '<div class="gg-fix mt-2"><i class="ti ti-tool me-1"></i><strong>' . $e(L::ui('fix', $lang)) . ':</strong> ' . $e($a['fix']) . '</div>'
                . $refs . '</div></div>';
        }

        // checks
        $s = $r->statusCounts();
        $rows = '';
        $badge = ['pass' => 'bg-green-lt', 'fail' => 'bg-orange-lt', 'skip' => 'bg-secondary-lt', 'error' => 'bg-red-lt'];
        $label = ['pass' => L::ui('passed', $lang), 'fail' => L::ui('failed', $lang), 'skip' => L::ui('skipped', $lang), 'error' => L::ui('errors', $lang)];
        foreach ($r->checks as $c) {
            $g = Check::GROUPS[$c['group']] ?? ['', ''];
            $rows .= '<tr><td><code>' . $e($c['id']) . '</code></td><td>' . $e(L::pick($c['name'], $lang)) . '<div class="text-muted small">' . $e($lang === 'tr' ? $g[1] : $g[0]) . '</div></td>'
                . '<td><span class="badge ' . ($badge[$c['status']] ?? '') . '">' . $e($label[$c['status']] ?? $c['status']) . '</span>'
                . ($c['note'] !== '' ? '<div class="text-muted small">' . $e($c['note']) . '</div>' : '') . '</td></tr>';
        }
        $out .= '<details class="card mt-4"><summary class="card-header" style="cursor:pointer">' . $e(L::ui('checks', $lang)) . ': '
            . $s['pass'] . ' ' . $e(L::ui('passed', $lang)) . ', ' . $s['fail'] . ' ' . $e(L::ui('failed', $lang)) . ', ' . $s['skip'] . ' ' . $e(L::ui('skipped', $lang)) . ', ' . $s['error'] . ' ' . $e(L::ui('errors', $lang))
            . '</summary><div class="table-responsive"><table class="table table-sm table-vcenter mb-0">' . $rows . '</table></div></details>';

        // history
        if (count($history) > 1) {
            $out .= self::history($history, (int) $row['id'], $lang);
        }
        return $out . '</div>';
    }

    private static function history(array $h, int $current, string $lang): string
    {
        $e = [self::class, 'e'];
        $pts = array_reverse(array_map(static fn($x) => (int) $x['score'], $h));
        $w = 260;
        $hgt = 48;
        $n = count($pts);
        $poly = [];
        foreach ($pts as $i => $v) {
            $poly[] = round($n > 1 ? $i * $w / ($n - 1) : 0, 1) . ',' . round($hgt - $v * $hgt / 100, 1);
        }
        $svg = '<svg width="' . $w . '" height="' . ($hgt + 4) . '" viewBox="0 -2 ' . $w . ' ' . ($hgt + 4) . '" aria-label="score trend"><polyline fill="none" stroke="currentColor" stroke-width="2" points="' . implode(' ', $poly) . '"/></svg>';
        $rows = '';
        foreach ($h as $x) {
            $cur = (int) $x['id'] === $current;
            $rows .= '<tr' . ($cur ? ' class="table-active"' : '') . '><td><a href="' . $e(Menu::url('front/index.php', ['id' => $x['id']])) . '">#' . (int) $x['id'] . '</a></td>'
                . '<td>' . $e($x['date_creation']) . '</td><td><span class="badge ' . (self::GRADE[$x['grade']] ?? 'bg-secondary') . ' text-white">' . $e($x['grade']) . '</span> ' . (int) $x['score'] . '</td>'
                . '<td class="small">' . (int) $x['critical'] . ' / ' . (int) $x['high'] . ' / ' . (int) $x['medium'] . ' / ' . (int) $x['low'] . '</td>'
                . '<td class="small text-muted">' . $e($x['source'] === 'cron' ? self::t('automatic', 'otomatik', $lang) : self::t('manual', 'elle', $lang)) . ' · GLPI ' . $e($x['glpi_version']) . '</td></tr>';
        }
        return '<div class="card mt-4"><div class="card-header d-flex flex-wrap gap-3 align-items-center"><h3 class="card-title mb-0 flex-grow-1">' . $e(self::t('History', 'Geçmiş', $lang)) . '</h3>'
            . '<span class="text-primary">' . $svg . '</span></div><div class="table-responsive"><table class="table table-sm table-vcenter mb-0"><thead><tr><th>#</th><th>'
            . $e(self::t('Date', 'Tarih', $lang)) . '</th><th>' . $e(L::ui('score', $lang)) . '</th><th>' . $e(self::t('Crit. / High / Med. / Low', 'Kritik / Yüksek / Orta / Düşük', $lang)) . '</th><th></th></tr></thead>'
            . $rows . '</table></div></div>';
    }

    private static function closeForm(): string
    {
        // Html::closeForm(false) adds GLPI's CSRF token (GLPI 10) and is harmless on 11/12
        return Html::closeForm(false);
    }

    private static function css(): string
    {
        return '<style>.gg-grade{width:84px;height:84px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:46px;font-weight:800;color:#fff}'
            . '.gg-ev{font:12.5px ui-monospace,Consolas,monospace;padding-left:18px;margin-top:6px;word-break:break-word}'
            . '.gg-fix{background:rgba(47,179,68,.09);border-radius:6px;padding:8px 10px}.gg-wrap summary::-webkit-details-marker{margin-right:4px}</style>';
    }
}
