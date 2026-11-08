<?php

declare(strict_types=1);

namespace GlpiGuard;

final class Result
{
    /** @var list<array{id:string,group:string,name:array,status:string,note:string}> */
    public array $checks = [];
    /** @var list<Finding> */
    public array $findings = [];
    public array $meta;

    /** Language-neutral form (both languages kept) for storage, e.g. the plugin's scan history. */
    public function toStorage(): array
    {
        return [
            'meta'     => $this->meta,
            'checks'   => $this->checks,
            'findings' => array_map(static fn(Finding $f) => [
                'check' => $f->check, 'severity' => $f->severity, 'title' => $f->title, 'detail' => $f->detail,
                'fix' => $f->fix, 'evidence' => $f->evidence, 'refs' => $f->refs,
            ], $this->findings),
        ];
    }

    public static function fromStorage(array $d): self
    {
        $r = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $r->meta = (array) ($d['meta'] ?? []);
        $r->checks = (array) ($d['checks'] ?? []);
        $r->findings = [];
        foreach ((array) ($d['findings'] ?? []) as $x) {
            $f = new Finding((string) $x['severity'], (array) $x['title'], (array) $x['detail'], (array) $x['fix'], (array) ($x['evidence'] ?? []), (array) ($x['refs'] ?? []));
            $f->check = (string) $x['check'];
            $r->findings[] = $f;
        }
        return $r;
    }

    public function __construct(Glpi $g, Advisories $adv)
    {
        $this->meta = [
            'tool'        => 'glpi-guard ' . Scanner::VERSION,
            'scanned_at'  => date('c', $g->now()),
            'glpi_root'   => $g->root(),
            'glpi'        => $g->version(),
            'php'         => PHP_VERSION,
            'runtime'     => $g->runtime(),
            'db'          => $g->hasDb() ? $g->dbInfo() : '',
            'db_error'    => $g->hasDb() ? '' : $g->dbError(),
            'advisory_db' => $adv->generated(),
            'advisories'  => $adv->count(),
        ];
    }

    public function add(Check $c, string $status, array $findings, string $note = ''): void
    {
        $this->checks[] = ['id' => $c->id(), 'group' => $c->group(), 'name' => $c->name(), 'status' => $status, 'note' => $note];
        foreach ($findings as $f) {
            $this->findings[] = $f;
        }
        usort($this->findings, static fn(Finding $a, Finding $b) => Finding::ORDER[$b->severity] <=> Finding::ORDER[$a->severity] ?: strcmp($a->check, $b->check));
    }

    public function score(): int
    {
        $s = 100;
        foreach ($this->findings as $f) {
            $s -= Finding::PENALTY[$f->severity];
        }
        return max(0, $s);
    }

    public function grade(): string
    {
        $s = $this->score();
        $worst = $this->worst();
        if ($worst === Finding::CRITICAL) {
            return 'F'; // one critical issue is enough to fail, whatever the rest
        }
        return $s >= 90 ? 'A' : ($s >= 80 ? 'B' : ($s >= 65 ? 'C' : ($s >= 50 ? 'D' : 'F')));
    }

    public function worst(): ?string
    {
        return $this->findings ? $this->findings[0]->severity : null;
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        $c = array_fill_keys(array_keys(Finding::ORDER), 0);
        foreach ($this->findings as $f) {
            $c[$f->severity]++;
        }
        return $c;
    }

    /** @return array<string,int> */
    public function statusCounts(): array
    {
        $c = ['pass' => 0, 'fail' => 0, 'skip' => 0, 'error' => 0];
        foreach ($this->checks as $x) {
            $c[$x['status']]++;
        }
        return $c;
    }

    public function toArray(string $lang): array
    {
        return [
            'meta'     => $this->meta,
            'score'    => $this->score(),
            'grade'    => $this->grade(),
            'counts'   => $this->counts(),
            'checks'   => array_map(static fn($c) => ['id' => $c['id'], 'name' => L::pick($c['name'], $lang), 'status' => $c['status'], 'note' => $c['note']], $this->checks),
            'findings' => array_map(static fn(Finding $f) => $f->toArray($lang), $this->findings),
        ];
    }
}
