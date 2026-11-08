<?php

declare(strict_types=1);

namespace GlpiGuard;

/** One problem found by a check. Texts are ['en' => ..., 'tr' => ...]. Evidence must never contain secrets. */
final class Finding
{
    public const CRITICAL = 'critical';
    public const HIGH = 'high';
    public const MEDIUM = 'medium';
    public const LOW = 'low';
    public const INFO = 'info';

    public const ORDER = [self::CRITICAL => 4, self::HIGH => 3, self::MEDIUM => 2, self::LOW => 1, self::INFO => 0];

    /** score penalty per severity (score starts at 100) */
    public const PENALTY = [self::CRITICAL => 25, self::HIGH => 12, self::MEDIUM => 5, self::LOW => 2, self::INFO => 0];

    public string $check = '';
    public string $severity;
    /** @var array<string,string> */
    public array $title;
    /** @var array<string,string> */
    public array $detail;
    /** @var array<string,string> */
    public array $fix;
    /** @var list<string> evidence lines (no secrets) */
    public array $evidence;
    /** @var list<string> reference URLs */
    public array $refs;

    /**
     * @param array<string,string> $title
     * @param array<string,string> $detail
     * @param array<string,string> $fix
     * @param list<string> $evidence
     * @param list<string> $refs
     */
    public function __construct(string $severity, array $title, array $detail, array $fix, array $evidence = [], array $refs = [])
    {
        if (!isset(self::ORDER[$severity])) {
            throw new \InvalidArgumentException('Unknown severity ' . $severity);
        }
        $this->severity = $severity;
        $this->title = $title;
        $this->detail = $detail;
        $this->fix = $fix;
        $this->evidence = array_values($evidence);
        $this->refs = array_values($refs);
    }

    public static function atLeast(string $severity, string $threshold): bool
    {
        return (self::ORDER[$severity] ?? 0) >= (self::ORDER[$threshold] ?? 99);
    }

    public function toArray(string $lang): array
    {
        return [
            'check'    => $this->check,
            'severity' => $this->severity,
            'title'    => L::pick($this->title, $lang),
            'detail'   => L::pick($this->detail, $lang),
            'fix'      => L::pick($this->fix, $lang),
            'evidence' => $this->evidence,
            'refs'     => $this->refs,
        ];
    }
}
