<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDO;
use PDOStatement;

abstract class Repository {
    private bool $managedTransaction = false;
    public function __construct(protected readonly PDO $pdo) {}

    protected function statement(string $sql, array $params = []): PDOStatement {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public function beginTransaction(): bool { return $this->managedTransaction ? true : $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->managedTransaction ? true : $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
    public function lastInsertId(): string|false { return $this->pdo->lastInsertId(); }

    protected function transaction(callable $work): mixed {
        // Reads after a competing transaction finishes must see its committed data.
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->pdo->beginTransaction();
        $this->managedTransaction = true;
        try {
            $result = $work();
            if ($this->pdo->inTransaction()) $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } finally {
            $this->managedTransaction = false;
        }
    }
}
