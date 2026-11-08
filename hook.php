<?php

/**
 * Install / uninstall. The plugin only owns one table (its scan history) and one automatic action.
 * It never changes GLPI's own data or settings.
 */

use GlpiPlugin\Glpiguard\Scan;

function plugin_glpiguard_install(): bool
{
    global $DB;

    $table = Scan::TABLE;
    if (!$DB->tableExists($table)) {
        $charset = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $sign = DBConnection::getDefaultPrimaryKeySignOption();
        $DB->doQuery("CREATE TABLE `$table` (
            `id` int $sign NOT NULL AUTO_INCREMENT,
            `date_creation` timestamp NULL DEFAULT NULL,
            `users_id` int $sign NOT NULL DEFAULT 0,
            `source` varchar(16) NOT NULL DEFAULT 'manual',
            `glpi_version` varchar(32) NOT NULL DEFAULT '',
            `score` int NOT NULL DEFAULT 0,
            `grade` varchar(2) NOT NULL DEFAULT '',
            `critical` int NOT NULL DEFAULT 0,
            `high` int NOT NULL DEFAULT 0,
            `medium` int NOT NULL DEFAULT 0,
            `low` int NOT NULL DEFAULT 0,
            `info` int NOT NULL DEFAULT 0,
            `result` longtext,
            PRIMARY KEY (`id`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation ROW_FORMAT=DYNAMIC");
    }
    CronTask::register(Scan::class, 'glpiguardscan', 7 * DAY_TIMESTAMP, [
        'mode'    => CronTask::MODE_EXTERNAL,
        'state'   => CronTask::STATE_WAITING,
        'comment' => 'GLPI Guard weekly security scan',
    ]);
    return true;
}

function plugin_glpiguard_uninstall(): bool
{
    global $DB;

    if ($DB->tableExists(Scan::TABLE)) {
        $DB->doQuery('DROP TABLE `' . Scan::TABLE . '`');
    }
    CronTask::unregister('glpiguard');
    return true;
}
