<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDOStatement;

class UsuarioEdicionRepository extends Repository {
    public function save_select_usuarios(array $params): PDOStatement {
        return $this->statement("SELECT id FROM usuarios WHERE usuario = ? AND id <> ? LIMIT 1", $params);
    }

    public function save_update_usuarios(array $params): PDOStatement {
        return $this->statement("UPDATE usuarios SET nombre=?, usuario=?, rol=?, activo=? WHERE id=?", $params);
    }

    public function save_update_usuarios_2(array $params): PDOStatement {
        return $this->statement("UPDATE usuarios SET password_hash=? WHERE id=?", $params);
    }

    public function load_select_usuarios(array $params): PDOStatement {
        return $this->statement("SELECT id, nombre, usuario, rol, activo FROM usuarios WHERE id = ? LIMIT 1", $params);
    }
}
