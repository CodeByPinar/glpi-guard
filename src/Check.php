<?php

declare(strict_types=1);

namespace GlpiGuard;

/** Base class of every check. A check only reads; it returns zero or more findings. */
abstract class Check
{
    public const GROUPS = [
        'A' => ['Versions & advisories', 'Sürüm ve bilinen açıklar'],
        'B' => ['Accounts & authentication', 'Hesaplar ve kimlik doğrulama'],
        'C' => ['API', 'API'],
        'D' => ['Files & exposure', 'Dosyalar ve açıklık'],
        'F' => ['GLPI configuration', 'GLPI yapılandırması'],
        'G' => ['Profiles & rights', 'Profiller ve yetkiler'],
    ];

    /** e.g. "GG-B01" */
    abstract public function id(): string;

    /** @return array<string,string> short name */
    abstract public function name(): array;

    /** @return list<Finding> */
    abstract public function run(Glpi $g, Advisories $adv): array;

    public function needsDb(): bool
    {
        return true;
    }

    public function group(): string
    {
        return substr($this->id(), 3, 1);
    }

    /** helper: create a finding already tagged with this check id */
    protected function finding(string $severity, array $title, array $detail, array $fix, array $evidence = [], array $refs = []): Finding
    {
        $f = new Finding($severity, $title, $detail, $fix, $evidence, $refs);
        $f->check = $this->id();
        return $f;
    }
}
