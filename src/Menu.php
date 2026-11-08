<?php

declare(strict_types=1);

namespace GlpiPlugin\Glpiguard;

use CommonGLPI;
use Plugin;
use Session;

/** Setup menu entry. Only users who can change the general configuration (super-admins) see the plugin. */
final class Menu extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return 'GLPI Guard';
    }

    public static function canView(): bool
    {
        return (bool) Session::haveRight('config', UPDATE);
    }

    public const ICON = 'ti ti-shield-check';

    /** web path of a plugin file, relative to GLPI's root_doc (GLPI 10 may serve plugins from /marketplace) */
    public static function path(string $file = ''): string
    {
        $base = '/plugins/glpiguard';
        if (version_compare(GLPI_VERSION, '11.0.0', '<') && method_exists(Plugin::class, 'getWebDir')) {
            $base = (string) Plugin::getWebDir('glpiguard', false);
            $base = '/' . ltrim($base, '/');
        }
        return $base . ($file !== '' ? '/' . ltrim($file, '/') : '');
    }

    public static function url(string $file = '', array $q = []): string
    {
        global $CFG_GLPI;
        return rtrim((string) $CFG_GLPI['root_doc'], '/') . self::path($file) . ($q ? '?' . http_build_query($q) : '');
    }

    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }
        return [
            'title' => self::getTypeName(),
            'page'  => self::path('front/index.php'),
            'icon'  => self::ICON,
        ];
    }

    /** report language from the GLPI session (tr_TR → tr) */
    public static function lang(): string
    {
        $q = (string) ($_GET['lang'] ?? '');
        if ($q === 'en' || $q === 'tr') { // explicit choice (e.g. to share an English report)
            return $q;
        }
        return stripos((string) ($_SESSION['glpilanguage'] ?? ''), 'tr') === 0 ? 'tr' : 'en';
    }
}
