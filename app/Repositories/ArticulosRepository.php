<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDOStatement;

class ArticulosRepository extends Repository {
    public function next_art_code(): string {
        // Genera ART-000001 basado en el mayor ID existente (robusto y simple)
        $st = $this->pdo->query("SELECT id FROM articulos ORDER BY id DESC LIMIT 1");
        $row = $st->fetch();
        $next = $row ? ((int)$row['id'] + 1) : 1;
        return 'ART-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
    }

    public function get_categories_all(): array {
        $st = $this->pdo->query("SELECT id, nombre, activo FROM categorias ORDER BY nombre ASC");
        return $st->fetchAll();
    }

    public function get_categories_active(): array {
        $st = $this->pdo->query("SELECT id, nombre, activo FROM categorias WHERE activo=1 ORDER BY nombre ASC");
        return $st->fetchAll();
    }

    public function listing(array $query): array {
        $q = trim((string)($query['q'] ?? ''));
        $cat = (int)($query['cat'] ?? 0);
        $estado_f = (string)($query['estado'] ?? '');
        $sql = "SELECT a.id, a.codigo, a.nombre, a.unidad, a.precio_unitario, a.cantidad_total, a.cantidad_activa, a.estado,
        c.nombre AS categoria
        FROM articulos a
        INNER JOIN categorias c ON c.id = a.categoria_id
        WHERE 1=1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (a.nombre LIKE ? OR a.codigo LIKE ?) ";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        if ($cat > 0) {
            $sql .= " AND a.categoria_id = ? ";
            $params[] = $cat;
        }
        if ($estado_f === 'ACTIVO' || $estado_f === 'INACTIVO') {
            $sql .= " AND a.estado = ? ";
            $params[] = $estado_f;
        }
        $sql .= " ORDER BY a.id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        return compact('q', 'cat', 'estado_f', 'rows');
    }

    public function create_select_categorias(array $params): PDOStatement {
        return $this->statement("SELECT activo FROM categorias WHERE id=? LIMIT 1", $params);
    }

    public function create_insert_into_articulos(array $params): PDOStatement {
        return $this->statement("INSERT INTO articulos
            (codigo, nombre, categoria_id, unidad, precio_unitario, cantidad_total, cantidad_activa, estado, ubicacion, observaciones)
            VALUES (?,?,?,?,?, ?,?, 'ACTIVO', ?, ?)", $params);
    }

    public function create_insert_into_movimientos_inventario(array $params): PDOStatement {
        return $this->statement("INSERT INTO movimientos_inventario
            (tipo, articulo_id, referencia_tipo, referencia_id, cantidad, nota, creado_por)
            VALUES ('ENTRADA', ?, 'MANUAL', NULL, ?, ?, ?)", $params);
    }

    public function update_select_articulos(array $params): PDOStatement {
        return $this->statement("SELECT categoria_id FROM articulos WHERE id=? LIMIT 1", $params);
    }

    public function update_select_categorias(array $params): PDOStatement {
        return $this->statement("SELECT activo FROM categorias WHERE id=? LIMIT 1", $params);
    }

    public function update_update_articulos(array $params): PDOStatement {
        return $this->statement("UPDATE articulos
            SET nombre=?, categoria_id=?, unidad=?, precio_unitario=?, estado=?, ubicacion=?, observaciones=?
            WHERE id=?", $params);
    }

    public function stock_select_articulos(array $params): PDOStatement {
        return $this->statement("SELECT id, cantidad_total, cantidad_activa FROM articulos WHERE id=? LIMIT 1", $params);
    }

    public function stock_update_articulos(array $params): PDOStatement {
        return $this->statement("UPDATE articulos SET cantidad_total=?, cantidad_activa=? WHERE id=?", $params);
    }

    public function stock_insert_into_movimientos_inventario(array $params): PDOStatement {
        return $this->statement("INSERT INTO movimientos_inventario
            (tipo, articulo_id, referencia_tipo, referencia_id, cantidad, nota, creado_por)
            VALUES (?, ?, 'MANUAL', NULL, ?, ?, ?)", $params);
    }

    public function toggle_select_articulos(array $params): PDOStatement {
        return $this->statement("SELECT estado FROM articulos WHERE id=? LIMIT 1", $params);
    }

    public function toggle_update_articulos(array $params): PDOStatement {
        return $this->statement("UPDATE articulos SET estado=? WHERE id=?", $params);
    }

    public function load_select_articulos(array $params): PDOStatement {
        return $this->statement("SELECT a.*, c.nombre AS categoria_nombre, c.activo AS categoria_activa
            FROM articulos a
            INNER JOIN categorias c ON c.id = a.categoria_id
            WHERE a.id = ? LIMIT 1", $params);
    }
}
