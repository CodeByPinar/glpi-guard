<?php

/**
 * GLPI Guard — download a stored scan as a standalone HTML or JSON report.
 */

use GlpiGuard\Report\HtmlReport;
use GlpiPlugin\Glpiguard\Menu;
use GlpiPlugin\Glpiguard\Scan;

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$scan = Scan::load(isset($_GET['id']) ? ((int) $_GET['id'] ?: null) : null);
if ($scan === null) {
    Html::displayNotFoundError();
    exit;
}
$lang = in_array($_GET['lang'] ?? '', ['en', 'tr'], true) ? $_GET['lang'] : Menu::lang();
$name = 'glpi-guard-' . (int) $scan['row']['id'] . '-' . preg_replace('~[^0-9]~', '', (string) $scan['row']['date_creation']);

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_GET['format'] ?? 'html') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '.json"');
    echo json_encode($scan['result']->toArray($lang), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '.html"');
    echo HtmlReport::render($scan['result'], $lang);
}
exit;
