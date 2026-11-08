<?php

declare(strict_types=1);

namespace GlpiGuard;

use GlpiGuard\Report\HtmlReport;
use GlpiGuard\Report\TextReport;

final class Cli
{
    private const USAGE = <<<TXT
glpi-guard %s — read-only security audit for your own GLPI installation

Usage:
  glpi-guard scan <glpi-dir> [options]
  glpi-guard checks [--lang tr]
  glpi-guard --version

Options:
  --format text|json|html   output format (default: text)
  --output <file>           write the report to a file instead of stdout
  --lang en|tr              report language (default: en, or tr when LANG starts with tr)
  --fail-on <severity>      exit code 1 when a finding of this severity or worse exists
                            (critical|high|medium|low|info|none, default: none)
  --only GG-A01,GG-B01      run only these checks
  --skip GG-F06             skip these checks
  --config-dir <dir>        GLPI config directory (default: auto-detected)
  --db-host/--db-user/--db-pass/--db-name   override the connection read from config_db.php
                            (tip: use a read-only database user)
  --no-db                   file-system checks only
  --advisories <file>       use another advisory database JSON
  --no-color                plain text output

Exit codes: 0 ok, 1 findings at/above --fail-on, 2 usage or runtime error.
glpi-guard never writes to GLPI, never tries exploits or logins. Audit only systems you are responsible for.

TXT;

    public static function main(array $argv): int
    {
        [$cmd, $args, $opt] = self::parse(array_slice($argv, 1));
        $lang = $opt['lang'] ?? (stripos((string) getenv('LANG'), 'tr') === 0 ? 'tr' : 'en');
        if (!in_array($lang, L::LANGS, true)) {
            $lang = 'en';
        }
        if (isset($opt['version'])) {
            fwrite(STDOUT, 'glpi-guard ' . Scanner::VERSION . PHP_EOL);
            return 0;
        }
        if ($cmd === 'checks') {
            foreach (Scanner::checks() as $c) {
                fwrite(STDOUT, sprintf('%s  %s%s', $c->id(), L::pick($c->name(), $lang), $c->needsDb() ? '' : ' [files]') . PHP_EOL);
            }
            return 0;
        }
        if ($cmd !== 'scan' || count($args) !== 1 || isset($opt['help'])) {
            fwrite($cmd === null || isset($opt['help']) ? STDOUT : STDERR, sprintf(self::USAGE, Scanner::VERSION));
            return $cmd === null || isset($opt['help']) ? 0 : 2;
        }
        $root = rtrim($args[0], '/\\');
        if (!Glpi::looksLikeGlpi($root)) {
            fwrite(STDERR, "Not a GLPI directory: $root" . PHP_EOL);
            return 2;
        }
        $failOn = $opt['fail-on'] ?? 'none';
        if ($failOn !== 'none' && !isset(Finding::ORDER[$failOn])) {
            fwrite(STDERR, "Unknown --fail-on value: $failOn" . PHP_EOL);
            return 2;
        }
        $list = static fn(?string $s) => $s === null ? [] : array_values(array_filter(array_map(static fn($x) => strtoupper(trim($x)), explode(',', $s))));
        try {
            $g = new Glpi($root, array_intersect_key($opt, array_flip(['config-dir', 'db-host', 'db-user', 'db-pass', 'db-name', 'no-db'])));
            $adv = new Advisories($opt['advisories'] ?? null);
            $res = Scanner::run($g, $adv, $list($opt['only'] ?? null), $list($opt['skip'] ?? null));
        } catch (\Throwable $e) {
            fwrite(STDERR, 'glpi-guard: ' . $e->getMessage() . PHP_EOL);
            return 2;
        }
        $format = $opt['format'] ?? 'text';
        $color = !isset($opt['no-color']) && !isset($opt['output']) && function_exists('posix_isatty') && @posix_isatty(STDOUT);
        switch ($format) {
            case 'json':
                $out = json_encode($res->toArray($lang), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                break;
            case 'html':
                $out = HtmlReport::render($res, $lang);
                break;
            case 'text':
                $out = TextReport::render($res, $lang, $color);
                break;
            default:
                fwrite(STDERR, "Unknown --format: $format" . PHP_EOL);
                return 2;
        }
        if (isset($opt['output'])) {
            if (@file_put_contents($opt['output'], $out) === false) {
                fwrite(STDERR, 'Cannot write ' . $opt['output'] . PHP_EOL);
                return 2;
            }
            fwrite(STDERR, sprintf('%s %d/100 (%s) → %s', L::ui('score', $lang), $res->score(), $res->grade(), $opt['output']) . PHP_EOL);
        } else {
            fwrite(STDOUT, $out);
        }
        if ($failOn !== 'none') {
            foreach ($res->findings as $f) {
                if (Finding::atLeast($f->severity, $failOn)) {
                    return 1;
                }
            }
        }
        return 0;
    }

    /** @return array{0:?string,1:list<string>,2:array<string,string>} */
    private static function parse(array $a): array
    {
        $cmd = null;
        $args = [];
        $opt = [];
        $flags = ['no-db', 'no-color', 'help', 'version'];
        for ($i = 0; $i < count($a); $i++) {
            $x = $a[$i];
            if ($x === '-h') {
                $x = '--help';
            }
            if (strpos($x, '--') === 0) {
                $k = substr($x, 2);
                $v = null;
                if (strpos($k, '=') !== false) {
                    [$k, $v] = explode('=', $k, 2);
                }
                if (in_array($k, $flags, true)) {
                    $opt[$k] = '1';
                    continue;
                }
                $opt[$k] = $v ?? (string) ($a[++$i] ?? '');
                continue;
            }
            if ($cmd === null) {
                $cmd = $x;
            } else {
                $args[] = $x;
            }
        }
        return [$cmd, $args, $opt];
    }
}
