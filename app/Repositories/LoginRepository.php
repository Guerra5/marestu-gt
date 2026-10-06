<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDOStatement;

class LoginRepository extends Repository {
    public function save_select_usuarios(array $params): PDOStatement {
        return $this->statement("SELECT id, nombre, usuario, rol, activo, password_hash
            FROM usuarios
            WHERE usuario = ?
            LIMIT 1", $params);
    }
}
