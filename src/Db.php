<?php

declare(strict_types=1);

namespace GlpiGuard;

/**
 * Read-only MySQL/MariaDB access (mysqli, like GLPI itself).
 * Only SELECT / SHOW statements are accepted and the session is switched to READ ONLY when the server supports it.
 */
class Db
{
    private \mysqli $conn;

    /** Reuse an existing connection (e.g. GLPI's own $DB->dbh when glpi-guard runs inside GLPI as a plugin). */
    public static function fromConnection(\mysqli $conn): self
    {
        $self = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $self->conn = $conn;
        return $self;
    }

    public function __construct(string $host, string $user, string $pass, string $name, ?int $port = null, ?string $socket = null)
    {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = mysqli_init();
        if ($conn === false) {
            throw new \RuntimeException('mysqli_init failed');
        }
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $port = $port ?? ((int) ini_get('mysqli.default_port') ?: 3306);
        $socket = $socket ?? (string) ini_get('mysqli.default_socket');
        if (!@$conn->real_connect($host, $user, $pass, $name, $port, $socket)) {
            throw new \RuntimeException('Database connection failed: ' . mysqli_connect_error());
        }
        $conn->set_charset('utf8mb4');
        @$conn->query('SET SESSION TRANSACTION READ ONLY'); // best effort; we never write anyway
        $this->conn = $conn;
    }

    /**
     * GLPI style host value: "host", "host:port" or "host:/path/to/socket".
     * @return array{0:string,1:?int,2:?string}
     */
    public static function splitHost(string $h): array
    {
        if (preg_match('~^(.*?):(\d+)$~', $h, $m)) {
            return [$m[1], (int) $m[2], null];
        }
        if (preg_match('~^(.*?):(/.+)$~', $h, $m)) {
            return [$m[1] !== '' ? $m[1] : 'localhost', null, $m[2]];
        }
        return [$h, null, null];
    }

    /**
     * @param list<scalar|null> $params
     * @return list<array<string,mixed>>
     */
    public function rows(string $sql, array $params = []): array
    {
        if (!preg_match('~^\s*(SELECT|SHOW)\b~i', $sql)) {
            throw new \LogicException('glpi-guard is read-only: refused statement');
        }
        $st = $this->conn->prepare($sql);
        if ($st === false) {
            throw new \RuntimeException('SQL error: ' . $this->conn->error);
        }
        if ($params) {
            $vals = array_map(static function ($v) {
                return $v === null ? null : (string) $v;
            }, array_values($params));
            $st->bind_param(str_repeat('s', count($vals)), ...$vals);
        }
        if (!$st->execute()) {
            throw new \RuntimeException('SQL error: ' . $st->error);
        }
        $res = $st->get_result();
        $out = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $st->close();
        return $out;
    }

    public function serverInfo(): string
    {
        return (string) $this->conn->server_info;
    }
}
