<?php

declare(strict_types=1);

namespace GlpiGuard\Tests;

use GlpiGuard\Glpi;
use GlpiGuard\SkipException;

/** Glpi double: rows() answers from registered SQL fragments, config() from an array. */
final class FakeGlpi extends Glpi
{
    /** @var array<string,string> */
    public array $cfg = [];
    /** @var array<string,list<array>> */
    private array $answers = [];
    private bool $withDb;

    public function __construct(string $version, string $root = '/nonexistent', bool $withDb = true)
    {
        parent::__construct($root, ['no-db' => '1']);
        $this->version = $version;
        $this->withDb = $withDb;
    }

    public function on(string $sqlFragment, array $rows): void
    {
        $this->answers[$sqlFragment] = $rows;
    }

    public function hasDb(): bool
    {
        return $this->withDb;
    }

    public function rows(string $sql, array $params = []): array
    {
        if (!$this->withDb) {
            throw new SkipException('no database connection');
        }
        $best = null;
        foreach ($this->answers as $frag => $rows) {
            if (strpos($sql, $frag) !== false && ($best === null || strlen($frag) > strlen($best))) {
                $best = $frag;
            }
        }
        return $best === null ? [] : $this->answers[$best];
    }

    public function config(string $name, ?string $default = null): ?string
    {
        return $this->cfg[$name] ?? $default;
    }

    public function columnExists(string $table, string $column): bool
    {
        return false;
    }

    public function now(): int
    {
        return strtotime('2026-11-08 12:00:00');
    }
}
