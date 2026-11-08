<?php

declare(strict_types=1);

namespace GlpiGuard;

/** Tiny bilingual text helper: every user-facing text exists in English and Turkish. */
final class L
{
    public const LANGS = ['en', 'tr'];

    /** @return array<string,string> */
    public static function t(string $en, string $tr): array
    {
        return ['en' => $en, 'tr' => $tr];
    }

    /** sprintf on both languages with the same arguments */
    public static function f(array $text, ...$args): array
    {
        $out = [];
        foreach ($text as $k => $v) {
            $out[$k] = vsprintf($v, $args);
        }
        return $out;
    }

    public static function pick(array $text, string $lang): string
    {
        return (string) ($text[$lang] ?? $text['en'] ?? reset($text) ?: '');
    }

    /** UI strings of the reports */
    public static function ui(string $key, string $lang): string
    {
        static $ui = [
            'report'      => ['GLPI security report', 'GLPI güvenlik raporu'],
            'score'       => ['Score', 'Puan'],
            'grade'       => ['Grade', 'Not'],
            'target'      => ['GLPI directory', 'GLPI klasörü'],
            'version'     => ['GLPI version', 'GLPI sürümü'],
            'php'         => ['PHP (CLI)', 'PHP (CLI)'],
            'db'          => ['Database', 'Veritabanı'],
            'scanned'     => ['Scanned at', 'Tarama zamanı'],
            'findings'    => ['Findings', 'Bulgular'],
            'checks'      => ['Checks', 'Kontroller'],
            'passed'      => ['passed', 'geçti'],
            'failed'      => ['with findings', 'bulgulu'],
            'skipped'     => ['skipped', 'atlandı'],
            'errors'      => ['errors', 'hata'],
            'fix'         => ['How to fix', 'Nasıl düzeltilir'],
            'evidence'    => ['Evidence', 'Kanıt'],
            'refs'        => ['References', 'Kaynaklar'],
            'none'        => ['No findings. Well done.', 'Bulgu yok. Tebrikler.'],
            'critical'    => ['Critical', 'Kritik'],
            'high'        => ['High', 'Yüksek'],
            'medium'      => ['Medium', 'Orta'],
            'low'         => ['Low', 'Düşük'],
            'info'        => ['Info', 'Bilgi'],
            'readonly'    => ['Read-only scan: nothing was changed. No exploit or login attempt was made.',
                              'Salt okunur tarama: hiçbir şey değiştirilmedi. Hiçbir açık denenmedi, giriş denemesi yapılmadı.'],
            'advisorydb'  => ['Advisory database', 'Açık veritabanı'],
            'skipreason'  => ['Skipped checks', 'Atlanan kontroller'],
            'nodb'        => ['no database connection', 'veritabanı bağlantısı yok'],
        ];
        $p = $ui[$key] ?? [$key, $key];
        return $lang === 'tr' ? $p[1] : $p[0];
    }
}
