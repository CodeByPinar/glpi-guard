<?php

/**
 * GLPI Guard — dashboard: latest (or ?id=) scan, "Scan now", history.
 */

use GlpiPlugin\Glpiguard\Menu;
use GlpiPlugin\Glpiguard\Panel;
use GlpiPlugin\Glpiguard\Scan;

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if (isset($_POST['scan'])) {
    $id = Scan::runNow('manual');
    Session::addMessageAfterRedirect(Menu::lang() === 'tr' ? 'Tarama tamamlandı.' : 'Scan finished.');
    Html::redirect(Menu::url('front/index.php', ['id' => $id]));
}

Html::header(Menu::getTypeName(), '', 'config', Menu::class);
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
echo Panel::render(Scan::load($id ?: null), Scan::history(20), Menu::lang());
Html::footer();
