<?php

/**
 * Builds the installable GLPI plugin archive: dist/glpiguard-<version>.tar.gz (+ .sha256).
 * Extract it into GLPI's plugins/ (or marketplace/) directory: it contains the "glpiguard" folder.
 *   php tools/build-plugin.php
 */

declare(strict_types=1);

$base = dirname(__DIR__);
preg_match("~PLUGIN_GLPIGUARD_VERSION', '([^']+)'~", (string) file_get_contents($base . '/setup.php'), $m);
$version = $m[1] ?? '0.0.0';
preg_match("~VERSION = '([^']+)'~", (string) file_get_contents($base . '/src/Scanner.php'), $m2);
if (($m2[1] ?? '') !== $version) {
    fwrite(STDERR, "Version mismatch: setup.php $version vs Scanner::VERSION " . ($m2[1] ?? '?') . "\n");
    exit(1);
}
$include = ['setup.php', 'hook.php', 'front', 'src', 'data', 'bin', 'LICENSE', 'README.md', 'CHANGELOG.md', 'SECURITY.md', 'composer.json'];
@mkdir($base . '/dist');
$tar = $base . "/dist/glpiguard-$version.tar";
foreach ([$tar, "$tar.gz"] as $f) {
    @unlink($f);
}
$p = new PharData($tar);
$n = 0;
foreach ($include as $item) {
    $path = $base . '/' . $item;
    if (is_file($path)) {
        $p->addFile($path, 'glpiguard/' . $item);
        $n++;
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
        $p->addFile($f->getPathname(), 'glpiguard/' . $rel);
        $n++;
    }
}
$p->compress(Phar::GZ);
unset($p);
@unlink($tar);
$gz = "$tar.gz";
$sha = hash_file('sha256', $gz);
file_put_contents("$gz.sha256", "$sha  " . basename($gz) . "\n");
// stable name for ".../releases/latest/download/glpiguard.tar.gz"
copy($gz, $base . '/dist/glpiguard.tar.gz');
file_put_contents($base . '/dist/glpiguard.tar.gz.sha256', "$sha  glpiguard.tar.gz\n");
printf("%s  %d files  %d KB  sha256 %s\n", 'dist/' . basename($gz), $n, filesize($gz) / 1024, $sha);
