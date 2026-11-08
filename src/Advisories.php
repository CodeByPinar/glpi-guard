<?php

declare(strict_types=1);

namespace GlpiGuard;

/** Offline advisory database shipped with the tool (data/advisories.json, built by tools/fetch-advisories.php). */
final class Advisories
{
    private array $data;

    public function __construct(?string $file = null)
    {
        $file = $file ?? Paths::data('advisories.json');
        $json = @file_get_contents($file);
        $d = $json !== false ? json_decode($json, true) : null;
        $this->data = is_array($d) ? $d : ['generated' => '', 'glpi' => [], 'plugins' => []];
    }

    public function generated(): string
    {
        return (string) ($this->data['generated'] ?? '');
    }

    public function count(): int
    {
        return count($this->data['glpi'] ?? []) + array_sum(array_map(static fn($p) => count($p['advisories'] ?? []), $this->data['plugins'] ?? []));
    }

    /** @return list<array> advisories affecting this GLPI version */
    public function forGlpi(string $version): array
    {
        return self::matching($this->data['glpi'] ?? [], $version);
    }

    /** @return list<array> advisories affecting this plugin (directory name) version */
    public function forPlugin(string $directory, string $version): array
    {
        return self::matching($this->data['plugins'][$directory]['advisories'] ?? [], $version);
    }

    public function hasPlugin(string $directory): bool
    {
        return isset($this->data['plugins'][$directory]);
    }

    /** @return list<string> GLPI branches ("10.0") that received a fix published in the last $days days */
    public function maintainedBranches(int $now, int $days = 365): array
    {
        $out = [];
        foreach ($this->data['glpi'] ?? [] as $a) {
            $t = strtotime((string) $a['published']);
            if ($t === false || $t < $now - $days * 86400) {
                continue;
            }
            foreach ($a['affected'] as $r) {
                foreach ($r['patched'] as $p) {
                    $out[Version::branch($p)] = true;
                }
            }
        }
        $b = array_keys($out);
        usort($b, 'version_compare');
        return $b;
    }

    /** highest "patched" version on the same major line among the given advisories (= minimum safe target) */
    public static function minimumSafe(array $advisories, string $version): ?string
    {
        $best = null;
        foreach ($advisories as $a) {
            foreach ($a['affected'] as $r) {
                if (!Version::affected($version, $r['range'], $r['patched'])) {
                    continue;
                }
                foreach ($r['patched'] as $p) {
                    if (Version::major($p) === Version::major($version) && ($best === null || version_compare($p, $best, '>'))) {
                        $best = $p;
                    }
                }
            }
        }
        return $best;
    }

    private static function matching(array $list, string $version): array
    {
        $out = [];
        foreach ($list as $a) {
            foreach ($a['affected'] as $r) {
                if (Version::affected($version, $r['range'], $r['patched'])) {
                    $out[] = $a;
                    break;
                }
            }
        }
        usort($out, static function ($x, $y) {
            return (Finding::ORDER[$y['severity']] ?? 0) <=> (Finding::ORDER[$x['severity']] ?? 0) ?: strcmp($y['published'], $x['published']);
        });
        return $out;
    }
}
