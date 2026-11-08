<?php

declare(strict_types=1);

namespace GlpiGuard\Report;

use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\L;
use GlpiGuard\Result;

/** Single self-contained HTML file (no external resources), light and dark theme. */
final class HtmlReport
{
    public static function render(Result $r, string $lang): string
    {
        $e = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $m = $r->meta;
        $grade = $r->grade();
        $chips = '';
        foreach ($r->counts() as $sev => $n) {
            $chips .= "<span class='chip s-$sev'><b>$n</b> " . $e(L::ui($sev, $lang)) . '</span>';
        }
        $cards = '';
        foreach ($r->findings as $f) {
            $a = $f->toArray($lang);
            $ev = $a['evidence'] ? '<details' . (count($a['evidence']) <= 4 ? ' open' : '') . '><summary>' . $e(L::ui('evidence', $lang)) . ' (' . count($a['evidence']) . ')</summary><ul class="ev">'
                . implode('', array_map(static fn($x) => '<li>' . $e($x) . '</li>', $a['evidence'])) . '</ul></details>' : '';
            $refs = $a['refs'] ? '<div class="refs">' . $e(L::ui('refs', $lang)) . ': ' . implode(' · ', array_map(static fn($u) => '<a href="' . $e($u) . '" rel="noopener noreferrer">' . $e(preg_replace('~^https?://(www\.)?~', '', $u)) . '</a>', $a['refs'])) . '</div>' : '';
            $cards .= "<article class='card s-{$f->severity}'><header><span class='sev'>" . $e(L::ui($f->severity, $lang)) . "</span><h3>" . $e($a['title']) . "</h3><code>" . $e($a['check']) . "</code></header>"
                . '<p>' . $e($a['detail']) . '</p>' . $ev
                . "<div class='fix'><b>" . $e(L::ui('fix', $lang)) . '</b><p>' . $e($a['fix']) . '</p></div>' . $refs . '</article>';
        }
        if ($cards === '') {
            $cards = "<div class='card s-ok'><h3>" . $e(L::ui('none', $lang)) . '</h3></div>';
        }
        $rows = '';
        foreach ($r->checks as $c) {
            $g = Check::GROUPS[$c['group']] ?? ['', ''];
            $rows .= "<tr class='st-{$c['status']}'><td><code>" . $e($c['id']) . '</code></td><td>' . $e(L::pick($c['name'], $lang)) . "<div class='muted'>" . $e($lang === 'tr' ? $g[1] : $g[0]) . '</div></td>'
                . "<td><span class='st'>" . $e($c['status']) . '</span>' . ($c['note'] !== '' ? "<div class='muted'>" . $e($c['note']) . '</div>' : '') . '</td></tr>';
        }
        $s = $r->statusCounts();
        $title = L::ui('report', $lang);
        $meta = [
            [L::ui('target', $lang), $m['glpi_root']],
            [L::ui('version', $lang), $m['glpi'] ?: '?'],
            [L::ui('db', $lang), $m['db'] ?: L::ui('nodb', $lang)],
            [L::ui('php', $lang), $m['php']],
            [L::ui('advisorydb', $lang), ($m['advisory_db'] ?: '-') . ' (' . $m['advisories'] . ')'],
            [L::ui('scanned', $lang), $m['scanned_at']],
        ];
        $metaHtml = implode('', array_map(static fn($x) => '<div><span>' . $e($x[0]) . '</span><b>' . $e($x[1]) . '</b></div>', $meta));
        return <<<HTML
<!doctype html>
<html lang="{$e($lang)}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$e($title)} · {$e($m['glpi'])}</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--text:#18202f;--mut:#6b7488;--line:#e3e7ef;--crit:#b3261e;--high:#d9480f;--med:#b7791f;--low:#1c7ed6;--info:#6b7488;--ok:#2b8a3e}
@media (prefers-color-scheme:dark){:root{--bg:#0f141c;--card:#171e29;--text:#e6ebf3;--mut:#9aa4b8;--line:#263042;--crit:#ff6b6b;--high:#ff922b;--med:#fcc419;--low:#4dabf7;--info:#9aa4b8;--ok:#51cf66}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}
main{max-width:980px;margin:0 auto;padding:28px 16px 48px}
.top{display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin-bottom:18px}
.grade{width:92px;height:92px;border-radius:22px;display:flex;align-items:center;justify-content:center;font-size:52px;font-weight:800;color:#fff;background:var(--ok)}
.g-C{background:var(--med)}.g-D{background:var(--high)}.g-F{background:var(--crit)}
h1{margin:0;font-size:24px}.score{color:var(--mut);margin-top:4px}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.chip{border:1px solid var(--line);background:var(--card);border-radius:999px;padding:2px 10px;font-size:13px}
.meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px 18px;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px 16px;margin-bottom:22px}
.meta span{display:block;color:var(--mut);font-size:12px}.meta b{font-weight:600;word-break:break-all}
h2{font-size:17px;margin:26px 0 10px}
.card{background:var(--card);border:1px solid var(--line);border-left:5px solid var(--info);border-radius:12px;padding:14px 16px;margin-bottom:12px}
.card header{display:flex;gap:10px;align-items:baseline;flex-wrap:wrap}.card h3{margin:0;font-size:16px;flex:1;min-width:200px}.card p{margin:8px 0}
.sev{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.s-critical{border-left-color:var(--crit)}.s-critical .sev,.chip.s-critical b{color:var(--crit)}
.s-high{border-left-color:var(--high)}.s-high .sev,.chip.s-high b{color:var(--high)}
.s-medium{border-left-color:var(--med)}.s-medium .sev,.chip.s-medium b{color:var(--med)}
.s-low{border-left-color:var(--low)}.s-low .sev,.chip.s-low b{color:var(--low)}
.s-ok{border-left-color:var(--ok)}
code{font:12.5px ui-monospace,Consolas,monospace;color:var(--mut)}
.ev{margin:6px 0 0;padding-left:18px;font:13px ui-monospace,Consolas,monospace;color:var(--mut);word-break:break-word}
details summary{cursor:pointer;color:var(--mut);font-size:13px}
.fix{background:color-mix(in srgb,var(--ok) 9%,transparent);border-radius:8px;padding:8px 12px;margin-top:10px}.fix p{margin:4px 0 0}
.refs{font-size:13px;color:var(--mut);margin-top:8px;word-break:break-all}.refs a{color:var(--low)}
table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden}
td{padding:8px 12px;border-top:1px solid var(--line);vertical-align:top}tr:first-child td{border-top:0}
.muted{color:var(--mut);font-size:12.5px}.st{font-size:12px;font-weight:700;text-transform:uppercase}
.st-pass .st{color:var(--ok)}.st-fail .st{color:var(--high)}.st-skip .st,.st-error .st{color:var(--mut)}
.wrap{overflow-x:auto}footer{color:var(--mut);font-size:13px;margin-top:26px}
</style></head><body><main>
<div class="top"><div class="grade g-{$e($grade)}">{$e($grade)}</div><div><h1>{$e($title)}</h1>
<div class="score">{$e(L::ui('score', $lang))}: <b>{$r->score()}</b>/100 · GLPI {$e($m['glpi'] ?: '?')}</div><div class="chips">$chips</div></div></div>
<div class="meta">$metaHtml</div>
<h2>{$e(L::ui('findings', $lang))} ({$e((string) count($r->findings))})</h2>
$cards
<h2>{$e(L::ui('checks', $lang))} — {$s['pass']} {$e(L::ui('passed', $lang))}, {$s['fail']} {$e(L::ui('failed', $lang))}, {$s['skip']} {$e(L::ui('skipped', $lang))}, {$s['error']} {$e(L::ui('errors', $lang))}</h2>
<div class="wrap"><table>$rows</table></div>
<footer>{$e($m['tool'])} · {$e(L::ui('readonly', $lang))}</footer>
</main></body></html>
HTML;
    }
}
