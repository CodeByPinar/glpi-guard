<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Glpi;

/** Shared read helpers for checks. */
final class Util
{
    public const READ = 1;
    public const UPDATE = 2;
    public const CREATE = 4;
    public const DELETE = 8;
    public const PURGE = 16;
    public const WRITE = self::UPDATE | self::CREATE | self::DELETE | self::PURGE;

    /** @return array<int,string> id => name of profiles that can change the general configuration ("super admin"-like) */
    public static function adminProfiles(Glpi $g): array
    {
        $out = [];
        foreach ($g->rows("SELECT p.id, p.name FROM glpi_profiles p JOIN glpi_profilerights r ON r.profiles_id = p.id WHERE r.name = 'config' AND (r.rights & 2) = 2") as $r) {
            $out[(int) $r['id']] = (string) $r['name'];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> active, not deleted users holding one of the given profiles (any entity) */
    public static function usersWithProfiles(Glpi $g, array $profileIds, string $extraCols = ''): array
    {
        if (!$profileIds) {
            return [];
        }
        $in = implode(',', array_map('intval', array_keys(array_flip($profileIds))));
        return $g->rows('SELECT DISTINCT u.id, u.name, u.last_login' . ($extraCols !== '' ? ', ' . $extraCols : '') . "
            FROM glpi_users u JOIN glpi_profiles_users pu ON pu.users_id = u.id
            WHERE pu.profiles_id IN ($in) AND u.is_active = 1 AND u.is_deleted = 0 ORDER BY u.name");
    }

    /** short list for evidence: "a, b, c … (+12)" */
    public static function names(array $names, int $max = 10): string
    {
        $names = array_values($names);
        $s = implode(', ', array_slice($names, 0, $max));
        return count($names) > $max ? $s . ' … (+' . (count($names) - $max) . ')' : $s;
    }

    public static function mask(string $secret): string
    {
        $l = strlen($secret);
        return $l <= 4 ? str_repeat('*', $l) : substr($secret, 0, 2) . str_repeat('*', min(8, $l - 4)) . substr($secret, -2);
    }
}
