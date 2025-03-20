<?php

namespace Eyika\Atom\Framework\Support\Database;

use PDO;
use PDOException;
use Exception;

class Connection
{
    protected PDO $pdo;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        // $this->connect();
    }

    /**
     * Establish a database connection.
     */
    public function connect(): self
    {
        try {
            $dsn = $this->getDsn();
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'] ?? null,
                $this->config['password'] ?? null,
                $this->getOptions()
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }

        return $this;
    }

    public function instance(): self
    {
        return $this;
    }

    /**
     * Get the Data Source Name (DSN) for the connection.
     */
    protected function getDsn(): string
    {
        return match ($this->config['driver']) {
            'mysql' => "mysql:host={$this->config['host']};dbname={$this->config['database']};charset={$this->config['charset']}",
            'sqlite' => "sqlite:{$this->config['database']}",
            'pgsql' => "pgsql:host={$this->config['host']};dbname={$this->config['database']};",
            'sqlsrv' => "sqlsrv:Server={$this->config['host']};Database={$this->config['database']}",
            default => throw new Exception("Unsupported database driver: {$this->config['driver']}"),
        };
    }

    /**
     * Get PDO options.
     */
    protected function getOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }

    /**
     * Execute a raw SQL query.
     */
    public function query(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return the first result.
     */
    public function first(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch();
    }

    /**
     * Insert data into a table.
     */
    public function insert(string $table, array $data): bool
    {
        $columns = implode(', ', array_keys($data));
        $values = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$values})";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute(array_values($data));
    }

    /**
     * Update data in a table.
     */
    public function update(string $table, array $data, string $condition, array $params = []): bool
    {
        $set = implode(', ', array_map(fn($key) => "$key = ?", array_keys($data)));

        $sql = "UPDATE {$table} SET {$set} WHERE {$condition}";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([...array_values($data), ...$params]);
    }

    /**
     * Delete data from a table.
     */
    public function delete(string $table, string $condition, array $params = []): bool
    {
        $sql = "DELETE FROM {$table} WHERE {$condition}";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Rollback the current transaction.
     */
    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Get the underlying PDO instance.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
