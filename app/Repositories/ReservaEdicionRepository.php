<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDOStatement;

class ReservaEdicionRepository extends Repository {
    use ReservationLock;

    public function hasOutstandingDelivery(int $id): bool {
        return (bool)$this->statement('SELECT 1 FROM reserva_detalle WHERE reserva_id=? AND entregado > devuelto + danado + perdido
            UNION ALL SELECT 1 FROM reserva_extras WHERE reserva_id=? AND entregado > devuelto LIMIT 1', [$id, $id])->fetch();
    }
    public function get_reserva(int $id): ?array {
        $st = $this->pdo->prepare("SELECT r.*, CONCAT(c.nombres,' ',c.apellidos) AS cliente, c.telefono
            FROM reservas r
            INNER JOIN clientes c ON c.id = r.cliente_id
            WHERE r.id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function get_articulos_activos(): array {
        $st = $this->pdo->query("SELECT a.id,
            a.codigo,
            a.nombre,
            a.categoria_id,
            a.cantidad_activa,
            a.precio_unitario,
            c.nombre AS categoria
            FROM articulos a
            INNER JOIN categorias c ON c.id=a.categoria_id
            WHERE a.estado='ACTIVO'
            ORDER BY c.nombre ASC, a.nombre ASC");
        return $st->fetchAll();
    }

    public function reserva_items(int $reserva_id): array {
        $st = $this->pdo->prepare("SELECT d.id, d.articulo_id, d.cantidad,
            a.codigo, a.nombre, a.precio_unitario,
            c.nombre AS categoria
            FROM reserva_detalle d
            INNER JOIN articulos a ON a.id=d.articulo_id
            INNER JOIN categorias c ON c.id=a.categoria_id
            WHERE d.reserva_id=?
            ORDER BY d.id DESC");
        $st->execute([$reserva_id]);
        return $st->fetchAll();
    }

    public function reserva_extras(int $reserva_id): array {
        $st = $this->pdo->prepare("SELECT id, descripcion, proveedor, cantidad, precio_unitario,
            (cantidad * precio_unitario) AS subtotal
            FROM reserva_extras
            WHERE reserva_id=?
            ORDER BY id DESC");
        $st->execute([$reserva_id]);
        return $st->fetchAll();
    }

    public function reserved_qty(int $articulo_id, string $salida, string $retorno, int $exclude_reserva_id): int {
        $sql = "
        SELECT COALESCE(SUM(d.cantidad),0) AS total
        FROM reservas r
        INNER JOIN reserva_detalle d ON d.reserva_id = r.id
        WHERE d.articulo_id = ?
        AND r.estado IN ('CONFIRMADA','ENTREGADA')
        AND r.id <> ?
        AND r.fecha_salida <= ?
        AND r.fecha_retorno >= ?
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute([$articulo_id, $exclude_reserva_id, $retorno, $salida]);
        $row = $st->fetch();
        return (int)($row['total'] ?? 0);
    }

    public function articulo_activo_stock(int $articulo_id): int {
        $st = $this->pdo->prepare("SELECT cantidad_activa
            FROM articulos
            WHERE id=?
            LIMIT 1");
        $st->execute([$articulo_id]);
        $row = $st->fetch();
        return $row ? (int)$row['cantidad_activa'] : 0;
    }

    public function add_mov(
        string $tipo,
        int $articulo_id,
        int $reserva_id,
        int $cantidad,
        string $nota,
        int $user_id
    ): void {
        $st = $this->pdo->prepare("INSERT INTO movimientos_inventario
            (tipo, articulo_id, referencia_tipo, referencia_id, cantidad, nota, creado_por)
            VALUES (?, ?, 'RESERVA', ?, ?, ?, ?)");
        $st->execute([
                $tipo,
                $articulo_id,
                $reserva_id,
                $cantidad,
                $nota,
                $user_id
        ]);
    }

    public function direccion_evento_update_reservas(array $params): PDOStatement {
        return $this->statement("UPDATE reservas SET direccion_evento=? WHERE id=? AND estado IN ('BORRADOR','CONFIRMADA')", $params);
    }

    public function add_item_select_reserva_detalle(array $params): PDOStatement {
        return $this->statement("SELECT id, cantidad
            FROM reserva_detalle
            WHERE reserva_id=?
            AND articulo_id=?
            LIMIT 1", $params);
    }

    public function add_item_update_reserva_detalle(array $params): PDOStatement {
        return $this->statement("UPDATE reserva_detalle
            SET cantidad=?
            WHERE id=?", $params);
    }

    public function add_item_insert_into_reserva_detalle(array $params): PDOStatement {
        return $this->statement("INSERT INTO reserva_detalle
            (reserva_id, articulo_id, cantidad)
            VALUES (?,?,?)", $params);
    }

    public function remove_item_delete_from_reserva_detalle(array $params): PDOStatement {
        return $this->statement("DELETE FROM reserva_detalle
            WHERE id=?
            AND reserva_id=?", $params);
    }

    public function add_extra_insert_into_reserva_extras(array $params): PDOStatement {
        return $this->statement("INSERT INTO reserva_extras
            (reserva_id, descripcion, proveedor, cantidad, precio_unitario)
            VALUES (?,?,?,?,?)", $params);
    }

    public function remove_extra_delete_from_reserva_extras(array $params): PDOStatement {
        return $this->statement("DELETE FROM reserva_extras
            WHERE id=?
            AND reserva_id=?", $params);
    }

    public function set_status_update_reservas(array $params): PDOStatement {
        return $this->statement("UPDATE reservas
            SET estado='CONFIRMADA'
            WHERE id=?", $params);
    }

    public function set_status_update_reservas_2(array $params): PDOStatement {
        return $this->statement("UPDATE reservas
            SET estado='CANCELADA'
            WHERE id=?", $params);
    }
}
