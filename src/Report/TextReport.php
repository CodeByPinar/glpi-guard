<?php

declare(strict_types=1);

namespace GlpiGuard\Report;

use GlpiGuard\Finding;
use GlpiGuard\L;
use GlpiGuard\Result;

final class TextReport
{
    private const COLORS = [Finding::CRITICAL => '1;37;41', Finding::HIGH => '1;31', Finding::MEDIUM => '1;33', Finding::LOW => '1;36', Finding::INFO => '0;37'];

    public static function render(Result $r, string $lang, bool $color): string
    {
        $c = static fn(string $code, string $s) => $color ? "\033[{$code}m{$s}\033[0m" : $s;
        $m = $r->meta;
        $o = [];
        $o[] = $c('1', 'glpi-guard — ' . L::ui('report', $lang));
        $o[] = sprintf('%-18s %s', L::ui('target', $lang), $m['glpi_root']);
        $o[] = sprintf('%-18s %s', L::ui('version', $lang), $m['glpi'] ?: '?');
        $o[] = sprintf('%-18s %s', L::ui('db', $lang), $m['db'] ?: L::ui('nodb', $lang) . ($m['db_error'] ? ' — ' . $m['db_error'] : ''));
        $o[] = sprintf('%-18s %s (%d)', L::ui('advisorydb', $lang), $m['advisory_db'] ?: '-', $m['advisories']);
        $o[] = '';
        $grade = $r->grade();
        $gc = ['A' => '1;32', 'B' => '1;32', 'C' => '1;33', 'D' => '1;31', 'F' => '1;37;41'][$grade];
        $counts = [];
        foreach ($r->counts() as $sev => $n) {
            if ($n) {
                $counts[] = $c(self::COLORS[$sev], $n . ' ' . L::ui($sev, $lang));
            }
        }
        $o[] = sprintf('%s %s   %s %d/100   %s', L::ui('grade', $lang), $c($gc, " $grade "), L::ui('score', $lang), $r->score(), implode('  ', $counts));
        $o[] = str_repeat('─', 78);
        if (!$r->findings) {
            $o[] = $c('1;32', L::ui('none', $lang));
        }
        foreach ($r->findings as $f) {
            $a = $f->toArray($lang);
            $o[] = $c(self::COLORS[$f->severity], sprintf('[%s]', L::ui($f->severity, $lang))) . ' ' . $c('1', $a['title']) . '  ' . $c('2', $a['check']);
            $o[] = self::wrap($a['detail'], '    ');
            foreach (array_slice($a['evidence'], 0, 8) as $e) {
                $o[] = $c('2', '    • ' . $e);
            }
            $o[] = self::wrap('→ ' . $a['fix'], '    ');
            $o[] = '';
        }
        $s = $r->statusCounts();
        $o[] = str_repeat('─', 78);
        $o[] = sprintf('%s: %d %s, %d %s, %d %s, %d %s', L::ui('checks', $lang), $s['pass'], L::ui('passed', $lang), $s['fail'], L::ui('failed', $lang), $s['skip'], L::ui('skipped', $lang), $s['error'], L::ui('errors', $lang));
        foreach ($r->checks as $x) {
            if ($x['status'] === 'skip' || $x['status'] === 'error') {
                $o[] = $c('2', sprintf('  %s %-7s %s — %s', $x['id'], $x['status'], L::pick($x['name'], $lang), $x['note']));
            }
        }
        $o[] = $c('2', L::ui('readonly', $lang));
        return implode(PHP_EOL, $o) . PHP_EOL;
    }

    private static function wrap(string $s, string $indent): string
    {
        return $indent . wordwrap($s, 74, PHP_EOL . $indent, true);
    }
}
