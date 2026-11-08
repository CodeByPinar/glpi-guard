<?php

declare(strict_types=1);

namespace GlpiPlugin\Glpiguard;

use GlpiGuard\Db;
use GlpiGuard\Glpi;

/**
 * Engine context when running inside GLPI: paths and version from GLPI's constants, database through GLPI's own
 * connection (no password handled by the plugin). The session is NOT switched to read-only — it is GLPI's.
 */
final class PluginContext extends Glpi
{
    public function __construct()
    {
        global $DB;
        parent::__construct(GLPI_ROOT, ['no-db' => '1']);
        $dbh = (function () {
            return $this->dbh; // public in GLPI 10, protected since 11
        })->call($DB);
        if ($dbh instanceof \mysqli) {
            $this->useDb(Db::fromConnection($dbh));
        }
    }

    public function version(): string
    {
        return defined('GLPI_VERSION') ? (string) GLPI_VERSION : parent::version();
    }

    public function configDir(): string
    {
        return defined('GLPI_CONFIG_DIR') ? rtrim((string) GLPI_CONFIG_DIR, '/') : parent::configDir();
    }

    public function varDir(): string
    {
        return defined('GLPI_VAR_DIR') ? rtrim((string) GLPI_VAR_DIR, '/') : parent::varDir();
    }

    public function runtime(): string
    {
        return 'web';
    }
}
