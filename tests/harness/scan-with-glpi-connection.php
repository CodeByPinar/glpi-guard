<?php

/**
 * Developer harness: boot a GLPI test installation with its own bootstrap file, then run glpi-guard on that
 * installation reusing GLPI's already-open database connection (no password handled by glpi-guard).
 *
 *   php tests/harness/scan-with-glpi-connection.php <bootstrap.php> <glpi-dir> [scan options…]
 */

declare(strict_types=1);

$boot = $argv[1] ?? '';
$root = $argv[2] ?? '';
if (!is_file($boot) || !is_dir($root)) {
    fwrite(STDERR, "usage: php scan-with-glpi-connection.php <bootstrap.php> <glpi-dir> [options]\n");
    exit(2);
}
$base = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($base): void {
    if (strpos($class, 'GlpiGuard\\') === 0) {
        $f = $base . '/src/' . str_replace('\\', '/', substr($class, 10)) . '.php';
        if (is_file($f)) {
            require $f;
        }
    }
});
$cwd = getcwd();
require $boot;
chdir($cwd);
global $DB;

$opts = array_slice($argv, 3);
$lang = in_array('tr', $opts, true) ? 'tr' : 'en';
$format = in_array('json', $opts, true) ? 'json' : (in_array('html', $opts, true) ? 'html' : 'text');

$g = new GlpiGuard\Glpi($root, ['no-db' => '1']);
$dbh = (function () {
    return $this->dbh; // protected since GLPI 11
})->call($DB);
$g->useDb(GlpiGuard\Db::fromConnection($dbh));
$res = GlpiGuard\Scanner::run($g, new GlpiGuard\Advisories());
if ($format === 'json') {
    echo json_encode($res->toArray($lang), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
} elseif ($format === 'html') {
    echo GlpiGuard\Report\HtmlReport::render($res, $lang);
} else {
    echo GlpiGuard\Report\TextReport::render($res, $lang, false);
}
