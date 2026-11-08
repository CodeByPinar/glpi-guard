<?php

/**
 * GLPI Guard — read-only security audit, as a GLPI plugin (GLPI 10, 11, 12).
 * The same engine (src/, namespace GlpiGuard) also runs from the command line: php bin/glpi-guard scan <glpi-dir>
 *
 * @license GPL-3.0-or-later
 */

use GlpiPlugin\Glpiguard\Menu;

define('PLUGIN_GLPIGUARD_VERSION', '0.1.1');
define('PLUGIN_GLPIGUARD_MIN_GLPI', '10.0.0');
define('PLUGIN_GLPIGUARD_MAX_GLPI', '12.0.99');

// engine classes (namespace GlpiGuard\ in src/); GLPI autoloads GlpiPlugin\Glpiguard\ from src/ itself
spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'GlpiGuard\\') === 0) {
        $f = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 10)) . '.php';
        if (is_file($f)) {
            require_once $f;
        }
    }
});

function plugin_version_glpiguard(): array
{
    return [
        'name'         => 'GLPI Guard',
        'version'      => PLUGIN_GLPIGUARD_VERSION,
        'author'       => 'Pınar Topuz (CodeByPinar)',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/CodeByPinar/glpi-guard',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_GLPIGUARD_MIN_GLPI, 'max' => PLUGIN_GLPIGUARD_MAX_GLPI],
            'php'  => ['min' => '7.4'],
        ],
    ];
}

function plugin_init_glpiguard(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glpiguard'] = true; // GLPI 10
    if (class_exists(Menu::class) && Menu::canView()) {
        $PLUGIN_HOOKS['menu_toadd']['glpiguard'] = ['config' => Menu::class];
    }
}

function plugin_glpiguard_check_prerequisites(): bool
{
    if (!extension_loaded('mysqli')) {
        echo 'mysqli extension required';
        return false;
    }
    return true;
}

function plugin_glpiguard_check_config($verbose = false): bool
{
    return true;
}
