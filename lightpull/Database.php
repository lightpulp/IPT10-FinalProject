<?php

declare(strict_types=1);

namespace Lightpull;

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/** Thin PDO wrapper. Every statement is prepared; values are always bound, never concatenated. */
final class Database
{
    private PDO $pdo;

    public function __construct(#[\SensitiveParameter] array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 3306),
            $config['name'] ?? '',
        );
        $this->pdo = new PDO($dsn, (string) ($config['user'] ?? ''), (string) ($config['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $type = match (true) {
                is_int($value), is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue(is_int($key) ? $key + 1 : ':' . ltrim($key, ':'), $value, $type);
        }
        $statement->execute();
        return $statement;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_map([$this, 'identifier'], array_keys($data));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->identifier($table),
            implode(', ', $columns),
            implode(', ', array_fill(0, count($data), '?')),
        );
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, int $id, array $data): void
    {
        $assignments = array_map(fn (string $column) => $this->identifier($column) . ' = ?', array_keys($data));
        $sql = sprintf('UPDATE %s SET %s WHERE id = ?', $this->identifier($table), implode(', ', $assignments));
        $this->query($sql, [...array_values($data), $id]);
    }

    public function delete(string $table, int $id): void
    {
        $this->query(sprintf('DELETE FROM %s WHERE id = ?', $this->identifier($table)), [$id]);
    }

    /** Runs $work inside a transaction and returns its result; rolls back on any exception. */
    public function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** A LIKE pattern matching $term anywhere, with % and _ in the term taken literally. */
    public static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }

    public static function isDuplicateKey(PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    /** Array keys become SQL identifiers, so they are restricted to lowercase letters and underscores. */
    private function identifier(string $name): string
    {
        if (!preg_match('/^[a-z_]+$/', $name)) {
            throw new InvalidArgumentException("Invalid SQL identifier [{$name}].");
        }
        return '`' . $name . '`';
    }
}
