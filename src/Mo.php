<?php

declare(strict_types=1);

namespace GlpiGuard;

/**
 * Minimal gettext .mo reader. GLPI stores event messages already translated into the server language, so to
 * recognise e.g. "Failed login for %1$s from IP %2$s" we read the translations shipped in GLPI's locales/.
 */
final class Mo
{
    /** @return array<string,string> msgid => msgstr (first plural form) */
    public static function read(string $file): array
    {
        $bin = @file_get_contents($file);
        if ($bin === false || strlen($bin) < 28) {
            return [];
        }
        $magic = unpack('V', substr($bin, 0, 4))[1];
        $f = $magic === 0x950412de ? 'V' : ($magic === 0xde120495 ? 'N' : null);
        if ($f === null) {
            return [];
        }
        $h = unpack($f . '5', substr($bin, 4, 20)); // revision, count, origOffset, transOffset, hashSize
        [$n, $oo, $to] = [$h[2], $h[3], $h[4]];
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $o = unpack($f . '2', substr($bin, $oo + $i * 8, 8));
            $t = unpack($f . '2', substr($bin, $to + $i * 8, 8));
            $id = substr($bin, $o[2], $o[1]);
            $str = substr($bin, $t[2], $t[1]);
            $id = explode("\0", $id)[0];
            $ctx = strpos($id, "\x04");
            if ($ctx !== false) {
                $id = substr($id, $ctx + 1);
            }
            $out[$id] = explode("\0", $str)[0];
        }
        return $out;
    }

    /**
     * All translations of $msgid found in $dir/*.mo (plus the msgid itself), turned into regexes where %1$s/%s
     * become wildcards.
     * @return list<string>
     */
    public static function patterns(string $dir, string $msgid): array
    {
        $texts = [$msgid => true];
        foreach (glob(rtrim($dir, '/') . '/*.mo') ?: [] as $file) {
            $tr = self::read($file)[$msgid] ?? '';
            if ($tr !== '') {
                $texts[$tr] = true;
            }
        }
        $out = [];
        foreach (array_keys($texts) as $t) {
            $parts = preg_split('~%(?:\d+\$)?[sd]~', $t);
            $out[] = '~^' . implode('(.*)', array_map(static fn($p) => preg_quote($p, '~'), $parts)) . '$~u';
        }
        return $out;
    }
}
