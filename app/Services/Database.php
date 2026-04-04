<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Dünner PDO-Wrapper: ausschließlich vorbereitete Statements für Abfragen mit Parametern.
 */
final class Database
{
    private PDO $pdo;

    /**
     * @param array{host:string,port?:int,name:string,user:string,password:string,charset?:string} $cfg
     */
    private function __construct(array $cfg)
    {
        $host = $cfg['host'];
        $port = (int) ($cfg['port'] ?? 3306);
        $name = $cfg['name'];
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        $this->pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * @param array{host:string,port?:int,name:string,user:string,password:string,charset?:string} $cfg
     */
    public static function connect(array $cfg): self
    {
        try {
            return new self($cfg);
        } catch (PDOException $e) {
            throw new RuntimeException('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->run($sql, $params);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->run($sql, $params);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->run($sql, $params);

        return $stmt->rowCount();
    }

    public function lastInsertId(): int
    {
        $id = (int) $this->pdo->lastInsertId();

        return $id;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    private function run(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this->pdo);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
