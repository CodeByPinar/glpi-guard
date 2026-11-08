<?php

declare(strict_types=1);

namespace GlpiGuard;

/**
 * Everything the checks may read about one GLPI installation. Nothing here writes.
 * Configuration files are parsed as text (never included/executed).
 */
class Glpi
{
    protected string $root;
    protected ?Db $db = null;
    protected string $dbError = '';
    protected ?string $version = null;
    protected ?string $configDir = null;
    protected ?string $varDir = null;
    /** @var array<string,string>|null */
    protected ?array $config = null;
    /** @var array<string,bool> */
    private array $tableCache = [];
    /** @var array<string,array<string,bool>> */
    private array $colCache = [];

    /** @param array<string,string> $opts config-dir, var-dir, db-host, db-user, db-pass, db-name, no-db */
    public function __construct(string $root, array $opts = [])
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->configDir = $opts['config-dir'] ?? null;
        $this->varDir = $opts['var-dir'] ?? null;
        if (empty($opts['no-db'])) {
            try {
                $c = $this->dbConfig();
                foreach (['host', 'user', 'pass', 'name'] as $k) {
                    if (isset($opts['db-' . $k])) {
                        $c[$k] = $opts['db-' . $k];
                    }
                }
                if ($c['host'] === '' || $c['name'] === '') {
                    throw new \RuntimeException('config_db.php not found or incomplete in ' . $this->configDir());
                }
                [$h, $port, $sock] = Db::splitHost($c['host']);
                $this->db = new Db($h, $c['user'], $c['pass'], $c['name'], $port, $sock);
            } catch (\Throwable $e) {
                $this->dbError = $e->getMessage();
            }
        } else {
            $this->dbError = 'disabled (--no-db)';
        }
    }

    public static function looksLikeGlpi(string $root): bool
    {
        return is_file($root . '/inc/includes.php') || is_file($root . '/src/autoload/constants.php') || is_file($root . '/inc/define.php');
    }

    public function root(): string
    {
        return $this->root;
    }

    public function path(string $rel): string
    {
        return $this->root . '/' . ltrim($rel, '/');
    }

    /** GLPI_VERSION from the code (not from the database). */
    public function version(): string
    {
        if ($this->version === null) {
            $this->version = '';
            foreach (['src/autoload/constants.php', 'inc/define.php'] as $f) {
                $txt = @file_get_contents($this->path($f));
                if ($txt !== false && preg_match("~define\\(\\s*['\"]GLPI_VERSION['\"]\\s*,\\s*['\"]([^'\"]+)['\"]~", $txt, $m)) {
                    $this->version = $m[1];
                    break;
                }
            }
        }
        return $this->version;
    }

    /** "10.0", "11.0" … */
    public function branch(): string
    {
        return Version::branch($this->version());
    }

    /** Value of a define() found in GLPI's downstream / local define files (text parse). */
    protected function definedPath(string $const): ?string
    {
        foreach (['inc/downstream.php', 'config/local_define.php'] as $f) {
            $txt = @file_get_contents($this->path($f));
            if ($txt !== false && preg_match("~define\\(\\s*['\"]" . preg_quote($const, '~') . "['\"]\\s*,\\s*['\"]([^'\"]+)['\"]~", $txt, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    public function configDir(): string
    {
        if ($this->configDir === null) {
            $this->configDir = $this->definedPath('GLPI_CONFIG_DIR') ?? $this->path('config');
        }
        return rtrim($this->configDir, '/');
    }

    public function varDir(): string
    {
        if ($this->varDir === null) {
            $this->varDir = $this->definedPath('GLPI_VAR_DIR') ?? $this->path('files');
        }
        return rtrim($this->varDir, '/');
    }

    /** @return array{host:string,user:string,pass:string,name:string} parsed from config_db.php (text, not executed) */
    public function dbConfig(): array
    {
        $txt = (string) @file_get_contents($this->configDir() . '/config_db.php');
        $get = static function (string $prop) use ($txt): string {
            return preg_match("~\\\$" . $prop . "\\s*=\\s*'((?:[^'\\\\]|\\\\.)*)'~", $txt, $m) ? stripcslashes($m[1]) : '';
        };
        return ['host' => $get('dbhost'), 'user' => $get('dbuser'), 'pass' => rawurldecode($get('dbpassword')), 'name' => $get('dbdefault')];
    }

    /** Use an already open connection instead of config_db.php (plugin mode, tests). */
    public function useDb(Db $db): void
    {
        $this->db = $db;
        $this->dbError = '';
    }

    public function hasDb(): bool
    {
        return $this->db !== null;
    }

    public function dbError(): string
    {
        return $this->dbError;
    }

    public function dbInfo(): string
    {
        return $this->db ? $this->db->serverInfo() : '';
    }

    /** @return list<array<string,mixed>> */
    public function rows(string $sql, array $params = []): array
    {
        if (!$this->db) {
            throw new SkipException('no database connection');
        }
        return $this->db->rows($sql, $params);
    }

    public function tableExists(string $table): bool
    {
        if (!isset($this->tableCache[$table])) {
            $this->tableCache[$table] = (bool) $this->rows('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
        }
        return $this->tableCache[$table];
    }

    public function columnExists(string $table, string $column): bool
    {
        if (!isset($this->colCache[$table])) {
            $this->colCache[$table] = [];
            foreach ($this->rows('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]) as $r) {
                $this->colCache[$table][strtolower((string) $r['COLUMN_NAME'])] = true;
            }
        }
        return isset($this->colCache[$table][strtolower($column)]);
    }

    /** glpi_configs (context core) */
    public function config(string $name, ?string $default = null): ?string
    {
        if ($this->config === null) {
            $this->config = [];
            foreach ($this->rows("SELECT name, value FROM glpi_configs WHERE context = 'core'") as $r) {
                $this->config[(string) $r['name']] = (string) $r['value'];
            }
        }
        return array_key_exists($name, $this->config) ? $this->config[$name] : $default;
    }

    /** "cli" for the command line tool, "web" when running inside GLPI (plugin) */
    public function runtime(): string
    {
        return getenv('GLPI_GUARD_RUNTIME') === 'docker' ? 'docker' : 'cli';
    }

    public function now(): int
    {
        return time();
    }

    public function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }
}
