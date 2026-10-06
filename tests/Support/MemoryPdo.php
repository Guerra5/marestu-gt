<?php
declare(strict_types=1);

/** Doble de PDO: registra SQL y permite respuestas deterministas sin servidor. */
final class MemoryPdo extends PDO {
    public array $calls = [];
    public array $transactions = [];
    private bool $transaction = false;
    public function __construct(public Closure $resolve) {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new MemoryStatement($this, $query);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
        $statement = $this->prepare($query);
        $statement->execute();
        return $statement;
    }
    public function exec(string $statement): int|false {
        $this->calls[] = ['sql' => $statement, 'params' => []];
        return 0;
    }
    public function beginTransaction(): bool { $this->transactions[] = 'begin'; return $this->transaction = true; }
    public function commit(): bool { $this->transactions[] = 'commit'; $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transactions[] = 'rollback'; $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function lastInsertId(?string $name = null): string|false { return '42'; }
}

final class MemoryStatement extends PDOStatement {
    private array $rows = [];
    private int $cursor = 0;
    public function __construct(private MemoryPdo $database, private string $sql) {}
    public function execute(?array $params = null): bool {
        $params ??= [];
        $this->database->calls[] = ['sql' => $this->sql, 'params' => $params];
        $this->rows = ($this->database->resolve)($this->sql, $params);
        $this->cursor = 0;
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed {
        return $this->rows[$this->cursor++] ?? false;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function rowCount(): int { return 1; }
}
