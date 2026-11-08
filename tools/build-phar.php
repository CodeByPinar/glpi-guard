<?php

/**
 * Builds dist/glpi-guard.phar (+ .sha256): one file, runs with any PHP 7.4+ that GLPI already uses.
 *   php -d phar.readonly=0 tools/build-phar.php
 */

declare(strict_types=1);

$base = dirname(__DIR__);
$version = (static function () use ($base): string {
    preg_match("~VERSION = '([^']+)'~", (string) file_get_contents($base . '/src/Scanner.php'), $m);
    return $m[1] ?? '0.0.0';
})();
@mkdir($base . '/dist');
$out = $base . '/dist/glpi-guard.phar';
@unlink($out);

$phar = new Phar($out, 0, 'glpi-guard.phar');
$phar->startBuffering();
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($files as $f) {
    $phar->addFile($f->getPathname(), 'src/' . str_replace('\\', '/', substr($f->getPathname(), strlen($base . '/src/'))));
}
foreach (['data/advisories.json', 'data/php.json', 'LICENSE', 'README.md'] as $f) {
    if (is_file($base . '/' . $f)) {
        $phar->addFile($base . '/' . $f, $f);
    }
}
$bin = (string) file_get_contents($base . '/bin/glpi-guard');
$bin = preg_replace('~^#!.*\R~', '', $bin);
$phar->addFromString('bin/glpi-guard', $bin);
$phar->setStub("#!/usr/bin/env php\n<?php\nPhar::mapPhar('glpi-guard.phar');\nrequire 'phar://glpi-guard.phar/bin/glpi-guard';\n__HALT_COMPILER();\n");
$phar->stopBuffering();
@chmod($out, 0755);
$sha = hash_file('sha256', $out);
file_put_contents($out . '.sha256', "$sha  glpi-guard.phar\n");
printf("dist/glpi-guard.phar  %s  %d KB  sha256 %s\n", $version, filesize($out) / 1024, $sha);
