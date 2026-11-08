<?php

declare(strict_types=1);

namespace GlpiGuard;

/** Version helpers for GitHub advisory ranges like ">= 9.2.0, < 11.0.0" or "<= 10.0.17". */
final class Version
{
    /** "10.0.20" → "10.0.20"; "11.0.0-rc2" stays comparable with version_compare */
    public static function normalize(string $v): string
    {
        $v = trim($v);
        $v = preg_replace('~^v~i', '', $v) ?? $v;
        return $v;
    }

    public static function branch(string $v): string
    {
        $p = explode('.', self::normalize($v));
        return ($p[0] ?? '0') . '.' . ($p[1] ?? '0');
    }

    public static function major(string $v): string
    {
        return explode('.', self::normalize($v))[0] ?? '0';
    }

    /** Does $v satisfy every comma-separated constraint of $range? A bare version means "=". */
    public static function satisfies(string $v, string $range): bool
    {
        $v = self::normalize($v);
        foreach (explode(',', $range) as $c) {
            $c = trim($c);
            if ($c === '') {
                continue;
            }
            if (!preg_match('~^(>=|<=|>|<|=|==)?\s*(\S+)$~', $c, $m)) {
                return false;
            }
            $op = $m[1] !== '' ? $m[1] : '==';
            if (!version_compare($v, self::normalize($m[2]), $op === '=' ? '==' : $op)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Advisory semantics (GitHub): the range is the vulnerable range; "patched" lists fixed versions. Some GLPI
     * advisories use an open range (">= 11.0.0") and put the upper bound only in "patched" ("11.0.10"), so a
     * version is affected when it is inside the range AND below the patched version of its own line.
     *
     * @param list<string> $patched
     */
    public static function affected(string $v, string $range, array $patched): bool
    {
        if (!self::satisfies($v, $range)) {
            return false;
        }
        $v = self::normalize($v);
        $same = array_values(array_filter($patched, static fn($p) => self::major($p) === self::major($v)));
        $cands = $same ?: $patched;
        foreach ($cands as $p) {
            if (version_compare($v, self::normalize($p), '>=')) {
                return false;
            }
        }
        return true;
    }
}
