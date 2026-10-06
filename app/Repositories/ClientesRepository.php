<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDOStatement;

class ClientesRepository extends Repository {
    public function listing(array $query): array {
        $q = trim((string)($query['q'] ?? ''));
        $estado_f = (string)($query['estado'] ?? '');
        $sql = "SELECT id, nombres, apellidos, telefono, email, nit, estado, creado_en
        FROM clientes
        WHERE 1=1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (
            nombres LIKE ? OR apellidos LIKE ? OR telefono LIKE ? OR email LIKE ? OR nit LIKE ?
          )";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        if ($estado_f === 'ACTIVO' || $estado_f === 'INACTIVO') {
            $sql .= " AND estado = ? ";
            $params[] = $estado_f;
        }
        $sql .= " ORDER BY id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        return compact('q', 'estado_f', 'rows');
    }

    public function create_insert_into_clientes(array $params): PDOStatement {
        return $this->statement("INSERT INTO clientes
            (nombres, apellidos, telefono, email, direccion, nit, observaciones, estado)
            VALUES (?,?,?,?,?,?,?,?)", $params);
    }

    public function update_update_clientes(array $params): PDOStatement {
        return $this->statement("UPDATE clientes SET
            nombres=?, apellidos=?, telefono=?, email=?, direccion=?, nit=?, observaciones=?, estado=?
            WHERE id=?", $params);
    }

    public function toggle_select_clientes(array $params): PDOStatement {
        return $this->statement("SELECT estado FROM clientes WHERE id=? LIMIT 1", $params);
    }

    public function toggle_update_clientes(array $params): PDOStatement {
        return $this->statement("UPDATE clientes SET estado=? WHERE id=?", $params);
    }

    public function load_select_clientes(array $params): PDOStatement {
        return $this->statement("SELECT * FROM clientes WHERE id = ? LIMIT 1", $params);
    }
}
