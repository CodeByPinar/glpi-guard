<?php

declare(strict_types=1);

namespace GlpiGuard;

final class Scanner
{
    public const VERSION = '0.1.1';

    /** @return list<Check> */
    public static function checks(): array
    {
        return [
            new Checks\A01GlpiAdvisories(),
            new Checks\A02GlpiBranch(),
            new Checks\A03PluginAdvisories(),
            new Checks\A04PhpVersion(),
            new Checks\A05SchemaVersion(),
            new Checks\B01DefaultAccounts(),
            new Checks\B02AdminAccounts(),
            new Checks\B03PasswordPolicy(),
            new Checks\B04TwoFactor(),
            new Checks\B05Ldap(),
            new Checks\B06FailedLogins(),
            new Checks\C01ApiAccess(),
            new Checks\C02AdminApiTokens(),
            new Checks\D01StrayFiles(),
            new Checks\D02Permissions(),
            new Checks\D03InactivePluginCode(),
            new Checks\D04VcsFolders(),
            new Checks\F01DebugMode(),
            new Checks\F02DocumentTypes(),
            new Checks\F03Anonymous(),
            new Checks\F04Mail(),
            new Checks\F05Cron(),
            new Checks\F06Https(),
            new Checks\G01PrivilegeEscalation(),
            new Checks\G02SelfServiceRights(),
        ];
    }

    /**
     * @param list<string> $only  check ids to run (empty = all)
     * @param list<string> $skip  check ids to skip
     */
    public static function run(Glpi $g, Advisories $adv, array $only = [], array $skip = []): Result
    {
        $res = new Result($g, $adv);
        foreach (self::checks() as $c) {
            $id = $c->id();
            if (($only && !in_array($id, $only, true)) || in_array($id, $skip, true)) {
                continue;
            }
            if ($c->needsDb() && !$g->hasDb()) {
                $res->add($c, 'skip', [], 'no database connection');
                continue;
            }
            try {
                $f = $c->run($g, $adv);
                $res->add($c, $f ? 'fail' : 'pass', $f);
            } catch (SkipException $e) {
                $res->add($c, 'skip', [], $e->getMessage());
            } catch (\Throwable $e) {
                $res->add($c, 'error', [], get_class($e) . ': ' . $e->getMessage());
            }
        }
        return $res;
    }
}
