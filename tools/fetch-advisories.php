<?php

/**
 * Builds data/advisories.json from GitHub Security Advisories (public, published).
 *
 *   php tools/fetch-advisories.php            (unauthenticated: 60 requests/hour)
 *   GITHUB_TOKEN=... php tools/fetch-advisories.php
 *
 * GLPI core: glpi-project/glpi. Plugins: a curated list of widely used plugin repositories (key = plugin directory).
 * Run by CI daily; the result is committed after review.
 */

declare(strict_types=1);

const PLUGINS = [
    // directory => owner/repo
    'fields'            => 'pluginsGLPI/fields',
    'formcreator'       => 'pluginsGLPI/formcreator',
    'datainjection'     => 'pluginsGLPI/datainjection',
    'genericobject'     => 'pluginsGLPI/genericobject',
    'tag'               => 'pluginsGLPI/tag',
    'behaviors'         => 'yllen/behaviors',
    'escalade'          => 'pluginsGLPI/escalade',
    'moreticket'        => 'InfotelGLPI/moreticket',
    'metademands'       => 'InfotelGLPI/metademands',
    'order'             => 'pluginsGLPI/order',
    'accounts'          => 'InfotelGLPI/accounts',
    'webapplications'   => 'InfotelGLPI/webapplications',
    'manageentities'    => 'InfotelGLPI/manageentities',
    'mreporting'        => 'pluginsGLPI/mreporting',
    'news'              => 'pluginsGLPI/news',
    'glpiinventory'     => 'glpi-project/glpi-inventory-plugin',
    'fusioninventory'   => 'fusioninventory/fusioninventory-for-glpi',
    'ocsinventoryng'    => 'pluginsGLPI/ocsinventoryng',
    'oauthimap'         => 'pluginsGLPI/oauthimap',
    'singlesignon'      => 'edgardmessias/glpi-singlesignon',
    'addressing'        => 'pluginsGLPI/addressing',
    'databaseinventory' => 'glpi-project/glpi-agent-database-inventory',
];

/** @return array{0:array,1:?string} decoded body + next page URL (cursor pagination via Link header) */
function gh(string $url): array
{
    $headers = "Accept: application/vnd.github+json
User-Agent: glpi-guard-advisory-fetch
X-GitHub-Api-Version: 2022-11-28
";
    if (getenv('GITHUB_TOKEN')) {
        $headers .= 'Authorization: Bearer ' . getenv('GITHUB_TOKEN') . "
";
    }
    $ctx = stream_context_create(['http' => ['header' => $headers, 'ignore_errors' => true, 'timeout' => 30]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    $next = null;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) {
            $status = (int) $m[1];
        }
        if (stripos($h, 'Link:') === 0 && preg_match('~<([^>]+)>;\s*rel="next"~', $h, $m)) {
            $next = $m[1];
        }
    }
    if ($status === 404) {
        return [[], null];
    }
    if ($status !== 200 || $body === false) {
        fwrite(STDERR, "GitHub API $url → HTTP $status
" . substr((string) $body, 0, 300) . "
");
        exit(1);
    }
    $d = json_decode($body, true);
    return [is_array($d) ? $d : [], $next];
}

function advisories(string $repo): array
{
    $all = [];
    $seen = [];
    $url = "https://api.github.com/repos/$repo/security-advisories?state=published&per_page=100";
    for ($i = 0; $url !== null && $i < 50; $i++) {
        [$chunk, $url] = gh($url);
        foreach ($chunk as $a) {
            if (!isset($seen[$a['ghsa_id']])) {
                $seen[$a['ghsa_id']] = true;
                $all[] = $a;
            }
        }
    }
    return $all;
}

function slim(array $a): ?array
{
    // withdrawn advisories and placeholders ("Advisory to be removed", duplicates) would raise false alarms
    if (!empty($a['withdrawn_at']) || preg_match('~to be removed|withdrawn|duplicate of~i', (string) ($a['summary'] ?? ''))) {
        return null;
    }
    $ranges = [];
    foreach ($a['vulnerabilities'] ?? [] as $v) {
        $range = trim((string) ($v['vulnerable_version_range'] ?? ''));
        if ($range === '') {
            continue;
        }
        $patched = array_values(array_filter(array_map('trim', explode(',', (string) ($v['patched_versions'] ?? '')))));
        $ranges[] = ['range' => $range, 'patched' => $patched];
    }
    if (!$ranges) {
        return null;
    }
    return [
        'id'        => $a['ghsa_id'],
        'cve'       => $a['cve_id'] ?? null,
        'severity'  => $a['severity'] ?? 'unknown',
        'cvss'      => $a['cvss_severities']['cvss_v3']['score'] ?? ($a['cvss']['score'] ?? null),
        'summary'   => trim((string) ($a['summary'] ?? '')),
        'published' => substr((string) ($a['published_at'] ?? ''), 0, 10),
        'url'       => $a['html_url'] ?? ('https://github.com/advisories/' . $a['ghsa_id']),
        'affected'  => $ranges,
    ];
}

$out = ['generated' => gmdate('Y-m-d'), 'source' => 'GitHub Security Advisories', 'glpi' => [], 'plugins' => []];
foreach (advisories('glpi-project/glpi') as $a) {
    if ($s = slim($a)) {
        $out['glpi'][] = $s;
    }
}
foreach (PLUGINS as $dir => $repo) {
    $list = [];
    foreach (advisories($repo) as $a) {
        if ($s = slim($a)) {
            $list[] = $s;
        }
    }
    if ($list) {
        $out['plugins'][$dir] = ['repo' => $repo, 'advisories' => $list];
    }
    fwrite(STDERR, sprintf("%-18s %3d\n", $dir, count($list)));
}
usort($out['glpi'], static fn($x, $y) => strcmp($y['published'], $x['published']));
$file = dirname(__DIR__) . '/data/advisories.json';
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDERR, sprintf("glpi %d advisories, %d plugins → %s\n", count($out['glpi']), count($out['plugins']), $file));
