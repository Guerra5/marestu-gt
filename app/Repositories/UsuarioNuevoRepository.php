<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDOStatement;

class UsuarioNuevoRepository extends Repository {
    public function save_select_usuarios(array $params): PDOStatement {
        return $this->statement("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1", $params);
    }

    public function save_insert_into_usuarios(array $params): PDOStatement {
        return $this->statement("INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo) VALUES (?,?,?,?,?)", $params);
    }
}
