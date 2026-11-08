<?php

/**
 * Dependency-free test runner:  php tests/run.php
 * Uses a FakeGlpi (in-memory rows) so checks can be tested without MySQL.
 */

declare(strict_types=1);

$base = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($base): void {
    if (strpos($class, 'GlpiGuard\\') === 0) {
        $f = $base . '/src/' . str_replace('\\', '/', substr($class, 10)) . '.php';
        if (is_file($f)) {
            require $f;
        }
    }
});
require __DIR__ . '/FakeGlpi.php';

use GlpiGuard\Advisories;
use GlpiGuard\Checks;
use GlpiGuard\Finding;
use GlpiGuard\Scanner;
use GlpiGuard\Version;
use GlpiGuard\Tests\FakeGlpi;

$n = 0;
$bad = 0;
function ok(bool $c, string $msg): void
{
    global $n, $bad;
    $n++;
    if (!$c) {
        $bad++;
    }
    echo ($c ? '  ok  ' : '  FAIL ') . $msg . PHP_EOL;
}
function sev(array $findings): array
{
    return array_map(static fn(Finding $f) => $f->severity, $findings);
}

$adv = new Advisories(__DIR__ . '/fixtures/advisories.json');

echo "Version\n";
ok(Version::satisfies('10.0.20', '>= 9.2.0, < 11.0.0'), 'range with two bounds');
ok(!Version::satisfies('11.0.1', '>= 9.2.0, < 11.0.0'), 'upper bound excluded');
ok(Version::satisfies('10.0.17', '<= 10.0.17') && !Version::satisfies('10.0.18', '<= 10.0.17'), '<= bound');
ok(Version::affected('11.0.8', '>= 11.0.0', ['11.0.10']), 'open range closed by patched version');
ok(!Version::affected('11.0.10', '>= 11.0.0', ['11.0.10']), 'patched version itself is safe');
ok(Version::affected('10.0.20', '>= 9.2.0, < 11.0.0', ['10.0.28']), '10.0 below patch');
ok(!Version::affected('10.0.28', '>= 9.2.0, < 11.0.0', ['10.0.28']), '10.0 at patch');
ok(Version::affected('9.5.13', '>= 9.2.0, < 11.0.0', ['10.0.28']), 'older major with no own patch stays affected');
ok(!Version::affected('11.0.12', '>= 9.2.0, < 11.0.0', ['10.0.28']), 'outside range');
ok(Version::branch('10.0.26') === '10.0', 'branch');

echo "Advisories\n";
ok(count($adv->forGlpi('11.0.8')) === 2, 'two advisories match 11.0.8');
ok(Advisories::minimumSafe($adv->forGlpi('11.0.8'), '11.0.8') === '11.0.10', 'minimum safe version = highest patched on the line');
ok($adv->forGlpi('11.0.10') === [], 'nothing for patched version');
ok(count($adv->forPlugin('fields', '1.21.0')) === 1 && $adv->forPlugin('fields', '1.21.22') === [], 'plugin advisory');
ok(in_array('10.0', $adv->maintainedBranches(strtotime('2026-11-08')), true) && !in_array('9.5', $adv->maintainedBranches(strtotime('2026-11-08')), true), 'maintained branches from recent fixes');

echo "Checks\n";
$g = new FakeGlpi('11.0.8');
$r = (new Checks\A01GlpiAdvisories())->run($g, $adv);
ok(sev($r) === ['high'] && strpos($r[0]->fix['en'], '11.0.10') !== false, 'A01 high + upgrade target');
$g9 = new FakeGlpi('9.5.13');
ok(sev((new Checks\A02GlpiBranch())->run($g9, $adv)) === ['high'], 'A02 flags 9.5 as unmaintained');
ok((new Checks\A02GlpiBranch())->run(new FakeGlpi('10.0.26'), $adv) === [], 'A02 accepts 10.0');

ok(Checks\B01DefaultAccounts::isDefaultPassword(password_hash('glpi', PASSWORD_DEFAULT), 'glpi'), 'bcrypt default detected');
ok(Checks\B01DefaultAccounts::isDefaultPassword(md5('tech'), 'tech'), 'legacy md5 default detected');
ok(!Checks\B01DefaultAccounts::isDefaultPassword(password_hash('S3cure!long', PASSWORD_DEFAULT), 'glpi'), 'changed password not flagged');
$g->on('FROM glpi_users WHERE name IN', [
    ['name' => 'glpi', 'password' => password_hash('glpi', PASSWORD_DEFAULT), 'authtype' => 1],
    ['name' => 'tech', 'password' => password_hash('other', PASSWORD_DEFAULT), 'authtype' => 1],
    ['name' => 'normal', 'password' => password_hash('normal', PASSWORD_DEFAULT), 'authtype' => 3], // LDAP: not checked
]);
ok(sev((new Checks\B01DefaultAccounts())->run($g, $adv)) === ['critical'], 'B01 only glpi/glpi flagged, LDAP ignored');

$g->cfg = ['use_password_security' => '0'];
$g->on('COUNT(*) AS c FROM glpi_users WHERE authtype = 1', [['c' => 5]]);
ok(sev((new Checks\B03PasswordPolicy())->run($g, $adv)) === ['medium'], 'B03 disabled policy');
$g->cfg = ['use_password_security' => '1', 'password_min_length' => '8'];
ok(sev((new Checks\B03PasswordPolicy())->run($g, $adv)) === ['low'], 'B03 short minimum');
$g->cfg = ['use_password_security' => '1', 'password_min_length' => '12'];
ok((new Checks\B03PasswordPolicy())->run($g, $adv) === [], 'B03 good policy');

$g->on('FROM glpi_authldaps', [['name' => 'AD', 'host' => 'dc1', 'port' => 389, 'use_tls' => 0], ['name' => 'AD2', 'host' => 'ldaps://dc2', 'port' => 636, 'use_tls' => 0]]);
$r = (new Checks\B05Ldap())->run($g, $adv);
ok(sev($r) === ['high'] && count($r[0]->evidence) === 1, 'B05 plain LDAP only');

$msgs = [];
for ($i = 0; $i < 150; $i++) {
    $msgs[] = ['message' => 'Failed login for admin from IP 10.0.0.' . ($i % 3)];
}
$msgs[] = ['message' => 'glpi log in from IP 10.0.0.9'];
$g->on("FROM glpi_events WHERE service = 'login'", $msgs);
$r = (new Checks\B06FailedLogins())->run($g, $adv);
ok(sev($r) === ['medium'] && strpos($r[0]->evidence[1], 'admin ×150') !== false, 'B06 counts failed logins only');

$g->cfg = ['enable_api' => '1', 'enable_api_login_credentials' => '1'];
$g->on('FROM glpi_apiclients', [['name' => 'full access from localhost', 'ipv4_range_start' => null, 'ipv4_range_end' => null, 'ipv6' => '', 'app_token' => '']]);
ok(sev((new Checks\C01ApiAccess())->run($g, $adv)) === ['high', 'medium'], 'C01 open client + credential login');
$g->cfg = ['enable_api' => '0'];
ok((new Checks\C01ApiAccess())->run($g, $adv) === [], 'C01 API disabled');

$g->on('FROM glpi_documenttypes', [['name' => 'PHP', 'ext' => 'php'], ['name' => 'SVG', 'ext' => 'svg'], ['name' => 'PDF', 'ext' => 'pdf']]);
ok(sev((new Checks\F02DocumentTypes())->run($g, $adv)) === ['critical', 'low'], 'F02 php critical, svg low, pdf ignored');

$g->cfg = ['url_base' => 'http://glpi.example.org'];
ok(sev((new Checks\F06Https())->run($g, $adv)) === ['high'], 'F06 http');
$g->cfg = ['url_base' => 'https://glpi.example.org'];
ok((new Checks\F06Https())->run($g, $adv) === [], 'F06 https');

$g->on("WHERE r.name IN ('config','profile')", [
    ['id' => 4, 'name' => 'Super-Admin', 'r' => 'config', 'rights' => 3], ['id' => 4, 'name' => 'Super-Admin', 'r' => 'profile', 'rights' => 23],
    ['id' => 6, 'name' => 'Technician', 'r' => 'profile', 'rights' => 3],
]);
$g->on('FROM glpi_users u JOIN glpi_profiles_users', [['id' => 9, 'name' => 'bob', 'last_login' => null]]);
ok(sev((new Checks\G01PrivilegeEscalation())->run($g, $adv)) === ['high'], 'G01 profile editor without config = escalation');

echo "Files\n";
$tmp = sys_get_temp_dir() . '/gg-test-' . bin2hex(random_bytes(4));
mkdir($tmp . '/public', 0777, true);
mkdir($tmp . '/plugins/fields/front', 0777, true);
touch($tmp . '/backup.sql');
touch($tmp . '/public/phpinfo.php');
touch($tmp . '/plugins/fields/front/test.php');
touch($tmp . '/test.php');
$fg = new FakeGlpi('11.0.8', $tmp);
$r = (new Checks\D01StrayFiles())->run($fg, $adv);
ok(sev($r) === ['high', 'medium'] && count($r[1]->evidence) === 2, 'D01 public + root files, plugin test.php ignored');
mkdir($tmp . '/.git');
ok(sev((new Checks\D04VcsFolders())->run($fg, $adv)) === ['low'], 'D04 .git at root');

echo "Scanner\n";
$nodb = new FakeGlpi('11.0.8', $tmp, false);
$res = Scanner::run($nodb, $adv);
$st = $res->statusCounts();
ok($st['error'] === 0 && $st['skip'] >= 15, 'without DB: DB checks skipped, no errors');
ok($res->grade() !== '' && $res->score() <= 100, 'score and grade');
$html = GlpiGuard\Report\HtmlReport::render($res, 'tr');
ok(strpos($html, '<script') === false && strpos($html, 'GLPI güvenlik raporu') !== false, 'HTML report: Turkish, no scripts');
$json = json_decode(json_encode($res->toArray('en')), true);
ok(isset($json['score'], $json['findings'], $json['checks']), 'JSON report shape');

echo "\n$n tests, $bad failed\n";
exit($bad ? 1 : 0);
