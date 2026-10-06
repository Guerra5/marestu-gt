<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDOStatement;

class UsuariosRepository extends Repository {
    public function listing(array $query): array {
        $q = trim((string)($query['q'] ?? ''));
        $sql = "SELECT id, nombre, usuario, rol, activo, creado_en
        FROM usuarios
        WHERE 1=1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (nombre LIKE ? OR usuario LIKE ? OR rol LIKE ?) ";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        $sql .= " ORDER BY id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return compact('q', 'rows');
    }

    public function create_select_usuarios(array $params): PDOStatement {
        return $this->statement("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1", $params);
    }

    public function create_insert_into_usuarios(array $params): PDOStatement {
        return $this->statement("INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo) VALUES (?,?,?,?,?)", $params);
    }

    public function toggle_select_usuarios(array $params): PDOStatement {
        return $this->statement("SELECT activo FROM usuarios WHERE id = ? LIMIT 1", $params);
    }

    public function toggle_update_usuarios(array $params): PDOStatement {
        return $this->statement("UPDATE usuarios SET activo = ? WHERE id = ?", $params);
    }

    public function reset_select_usuarios(array $params): PDOStatement {
        return $this->statement("SELECT id, usuario FROM usuarios WHERE id = ? LIMIT 1", $params);
    }

    public function reset_update_usuarios(array $params): PDOStatement {
        return $this->statement("UPDATE usuarios SET password_hash = ? WHERE id = ?", $params);
    }
}
