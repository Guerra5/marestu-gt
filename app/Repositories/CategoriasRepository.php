<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDOStatement;

class CategoriasRepository extends Repository {
    public function count_articulos_in_categoria(int $categoria_id): int {
        $st = $this->pdo->prepare("SELECT COUNT(*) AS n FROM articulos WHERE categoria_id = ?");
        $st->execute([$categoria_id]);
        $row = $st->fetch();
        return (int)($row['n'] ?? 0);
    }

    public function listing(array $query): array {
        $q = trim((string)($query['q'] ?? ''));
        $sql = "
        SELECT
        c.id,
        c.nombre,
        c.activo,
        c.creado_en,
        COUNT(a.id) AS articulos
        FROM categorias c
        LEFT JOIN articulos a ON a.categoria_id = c.id
        WHERE 1=1
        ";
        $params = [];
        if ($q !== '') {
            $sql .= " AND c.nombre LIKE ? ";
            $params[] = "%{$q}%";
        }
        $sql .= "
          GROUP BY c.id, c.nombre, c.activo, c.creado_en
          ORDER BY c.nombre ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return compact('q', 'rows');
    }

    public function create_select_categorias(array $params): PDOStatement {
        return $this->statement("SELECT id FROM categorias WHERE LOWER(nombre) = LOWER(?) LIMIT 1", $params);
    }

    public function create_insert_into_categorias(array $params): PDOStatement {
        return $this->statement("INSERT INTO categorias (nombre, activo) VALUES (?,?)", $params);
    }

    public function update_select_categorias(array $params): PDOStatement {
        return $this->statement("SELECT id FROM categorias WHERE LOWER(nombre) = LOWER(?) AND id <> ? LIMIT 1", $params);
    }

    public function update_update_categorias(array $params): PDOStatement {
        return $this->statement("UPDATE categorias SET nombre = ?, activo = ? WHERE id = ?", $params);
    }

    public function toggle_select_categorias(array $params): PDOStatement {
        return $this->statement("SELECT activo FROM categorias WHERE id = ? LIMIT 1", $params);
    }

    public function toggle_update_categorias(array $params): PDOStatement {
        return $this->statement("UPDATE categorias SET activo = ? WHERE id = ?", $params);
    }

    public function load_select_categorias(array $params): PDOStatement {
        return $this->statement("SELECT id, nombre, activo FROM categorias WHERE id = ? LIMIT 1", $params);
    }
}
