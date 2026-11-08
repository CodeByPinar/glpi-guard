<?php

declare(strict_types=1);

namespace GlpiGuard;

final class Paths
{
    public static function base(): string
    {
        return dirname(__DIR__);
    }

    public static function data(string $file): string
    {
        return self::base() . '/data/' . $file;
    }
}
