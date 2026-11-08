<?php

declare(strict_types=1);

namespace GlpiPlugin\Glpiguard;

use CommonDBTM;
use CronTask;
use GlpiGuard\Advisories;
use GlpiGuard\Result;
use GlpiGuard\Scanner;
use Session;

/** One stored scan (history) + the weekly automatic action. */
final class Scan extends CommonDBTM
{
    public const TABLE = 'glpi_plugin_glpiguard_scans';
    public const KEEP = 100;

    public static function getTypeName($nb = 0)
    {
        return 'GLPI Guard';
    }

    public static function canView(): bool
    {
        return Menu::canView();
    }

    public static function canCreate(): bool
    {
        return Menu::canView();
    }

    /** Runs the engine and stores the result. @return int new scan id */
    public static function runNow(string $source = 'manual'): int
    {
        global $DB;
        $res = Scanner::run(new PluginContext(), new Advisories());
        $c = $res->counts();
        $fields = [
            'date_creation' => date('Y-m-d H:i:s'),
            'users_id'      => (int) (Session::getLoginUserID() ?: 0),
            'source'        => $source,
            'glpi_version'  => (string) ($res->meta['glpi'] ?? ''),
            'score'         => $res->score(),
            'grade'         => $res->grade(),
            'critical'      => $c['critical'],
            'high'          => $c['high'],
            'medium'        => $c['medium'],
            'low'           => $c['low'],
            'info'          => $c['info'],
            'result'        => json_encode($res->toStorage(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        if (version_compare(GLPI_VERSION, '11.0.0', '<')) {
            // GLPI 10 expects already-escaped values in DB::insert (GLPI 11+ escapes itself)
            foreach ($fields as $k => $v) {
                if (is_string($v)) {
                    $fields[$k] = $DB->escape($v);
                }
            }
        }
        if (!$DB->insert(self::TABLE, $fields)) {
            throw new \RuntimeException('GLPI Guard: the scan result could not be stored.');
        }
        $id = (int) $DB->insertId();
        self::prune();
        return $id;
    }

    /** keep only the last KEEP scans */
    private static function prune(): void
    {
        global $DB;
        $old = [];
        foreach ($DB->request(['SELECT' => 'id', 'FROM' => self::TABLE, 'ORDER' => 'id DESC', 'START' => self::KEEP, 'LIMIT' => 1000]) as $r) {
            $old[] = (int) $r['id'];
        }
        if ($old) {
            $DB->delete(self::TABLE, ['id' => $old]);
        }
    }

    /** @return array{row:array,result:Result}|null */
    public static function load(?int $id = null): ?array
    {
        global $DB;
        $crit = ['FROM' => self::TABLE, 'ORDER' => 'id DESC', 'LIMIT' => 1];
        if ($id) {
            $crit['WHERE'] = ['id' => $id];
        }
        $row = $DB->request($crit)->current();
        if (!$row) {
            return null;
        }
        $data = json_decode((string) $row['result'], true);
        return ['row' => $row, 'result' => Result::fromStorage(is_array($data) ? $data : [])];
    }

    /** @return list<array> newest first */
    public static function history(int $limit = 20): array
    {
        global $DB;
        $out = [];
        foreach ($DB->request(['SELECT' => ['id', 'date_creation', 'source', 'glpi_version', 'score', 'grade', 'critical', 'high', 'medium', 'low', 'users_id'],
            'FROM' => self::TABLE, 'ORDER' => 'id DESC', 'LIMIT' => $limit]) as $r) {
            $out[] = $r;
        }
        return $out;
    }

    // ------------------------------------------------------------------ automatic action

    public static function cronInfo($name)
    {
        return ['description' => 'GLPI Guard — ' . (Menu::lang() === 'tr' ? 'haftalık güvenlik taraması' : 'weekly security scan')];
    }

    public static function cronGlpiguardscan($task = null)
    {
        $id = self::runNow('cron');
        if ($task instanceof CronTask) {
            $task->addVolume(1);
            $task->log('scan #' . $id);
        }
        return 1;
    }
}
