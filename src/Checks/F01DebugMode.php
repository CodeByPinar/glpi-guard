<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;

final class F01DebugMode extends Check
{
    private const DEBUG_MODE = 2;

    public function id(): string
    {
        return 'GG-F01';
    }

    public function name(): array
    {
        return L::t('Users working in debug mode', 'Hata ayıklama kipinde çalışan kullanıcılar');
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        $users = $g->rows('SELECT name FROM glpi_users WHERE use_mode = ? AND is_active = 1 AND is_deleted = 0 ORDER BY name', [self::DEBUG_MODE]);
        if (!$users) {
            return [];
        }
        return [$this->finding(
            Finding::LOW,
            L::f(L::t('%d accounts have debug mode enabled', '%d hesapta hata ayıklama kipi açık'), count($users)),
            L::t(
                'Debug mode shows SQL queries, internal paths and variables in the browser and slows GLPI down. It is meant for short troubleshooting only.',
                'Hata ayıklama kipi tarayıcıda SQL sorgularını, iç yolları ve değişkenleri gösterir, GLPI\'yi yavaşlatır. Yalnızca kısa süreli sorun giderme içindir.'
            ),
            L::t('My settings > Personalization (or the user form): switch back to normal mode.', 'Ayarlarım > Kişiselleştirme (ya da kullanıcı formu): normal kipe döndürün.'),
            [Util::names(array_column($users, 'name'))]
        )];
    }
}
