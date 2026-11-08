<?php

declare(strict_types=1);

namespace GlpiGuard\Checks;

use GlpiGuard\Advisories;
use GlpiGuard\Check;
use GlpiGuard\Finding;
use GlpiGuard\Glpi;
use GlpiGuard\L;
use GlpiGuard\SkipException;

/** Unix permissions of secrets (config_db.php, glpicrypt.key) and code directories. */
final class D02Permissions extends Check
{
    private const CODE_DIRS = ['src', 'inc', 'front', 'ajax', 'public', 'plugins', 'marketplace', 'vendor', 'config'];

    public function id(): string
    {
        return 'GG-D02';
    }

    public function name(): array
    {
        return L::t('File permissions of secrets and code', 'Gizli dosyaların ve kodun izinleri');
    }

    public function needsDb(): bool
    {
        return false;
    }

    public function run(Glpi $g, Advisories $adv): array
    {
        if ($g->isWindows()) {
            throw new SkipException('Unix permissions not applicable on Windows');
        }
        $out = [];
        $readable = [];
        $writable = [];
        foreach (['config_db.php', 'glpicrypt.key'] as $f) {
            $p = $g->configDir() . '/' . $f;
            $m = @fileperms($p);
            if ($m === false) {
                continue;
            }
            if ($m & 0002) {
                $writable[] = $p . ' ' . self::mode($m);
            } elseif ($m & 0004) {
                $readable[] = $p . ' ' . self::mode($m);
            }
        }
        if ($writable) {
            $out[] = $this->finding(
                Finding::CRITICAL,
                L::t('GLPI secret files are writable by every local user', 'GLPI gizli dosyaları sunucudaki her kullanıcı tarafından yazılabilir'),
                L::t('Anyone with a shell account can change the database connection or the encryption key.', 'Sunucuda kabuk hesabı olan herkes veritabanı bağlantısını ya da şifreleme anahtarını değiştirebilir.'),
                L::t('chmod 640 (owner: web server user or root, group: web server group).', 'chmod 640 (sahip: web sunucusu kullanıcısı ya da root, grup: web sunucusu grubu).'),
                $writable
            );
        }
        if ($readable) {
            $out[] = $this->finding(
                Finding::HIGH,
                L::t('GLPI secret files are readable by every local user', 'GLPI gizli dosyaları sunucudaki her kullanıcı tarafından okunabilir'),
                L::t('config_db.php holds the database password; glpicrypt.key decrypts stored passwords (LDAP, mail, SMTP, API tokens).',
                     'config_db.php veritabanı şifresini, glpicrypt.key ise kayıtlı şifreleri (LDAP, posta, SMTP, API belirteçleri) çözen anahtarı içerir.'),
                L::t('chmod 640 config_db.php glpicrypt.key (group = web server group).', 'chmod 640 config_db.php glpicrypt.key (grup = web sunucusu grubu).'),
                $readable
            );
        }
        $ww = [];
        foreach (self::CODE_DIRS as $d) {
            $p = $g->path($d);
            $m = @fileperms($p);
            if ($m !== false && ($m & 0002)) {
                $ww[] = $d . '/ ' . self::mode($m);
            }
        }
        if ($ww) {
            $out[] = $this->finding(
                Finding::HIGH,
                L::t('Code directories are world-writable', 'Kod klasörleri herkes tarafından yazılabilir'),
                L::t('Any local user or compromised service can drop PHP code that the web server will execute.', 'Herhangi bir yerel kullanıcı ya da ele geçirilmiş bir servis, web sunucusunun çalıştıracağı PHP kodu bırakabilir.'),
                L::t('Remove the "other write" bit (chmod -R o-w) and keep code owned by root or a deploy user.', '"Diğerleri yazabilir" iznini kaldırın (chmod -R o-w); kodun sahibi root ya da bir dağıtım kullanıcısı olsun.'),
                $ww
            );
        }
        return $out;
    }

    private static function mode(int $m): string
    {
        return sprintf('(%04o)', $m & 07777);
    }
}
