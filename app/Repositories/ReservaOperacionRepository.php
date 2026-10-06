<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDOStatement;

class ReservaOperacionRepository extends Repository {
    use ReservationLock;
    public function get_reserva(int $id): ?array {
        $st = $this->pdo->prepare("
            SELECT
            r.*,
            CONCAT(c.nombres,' ',c.apellidos) AS cliente,
            c.telefono,
            c.direccion AS direccion_cliente
            FROM reservas r
            INNER JOIN clientes c ON c.id = r.cliente_id
            WHERE r.id = ?
            LIMIT 1
            ");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function get_detalle(int $reserva_id): array {
        $st = $this->pdo->prepare("
            SELECT
            d.id,
            d.articulo_id,
            d.cantidad,
            d.entregado,
            d.devuelto,
            d.danado,
            d.perdido,
            a.codigo,
            a.nombre,
            a.cantidad_total,
            a.cantidad_activa,
            c.nombre AS categoria
            FROM reserva_detalle d
            INNER JOIN articulos a ON a.id = d.articulo_id
            INNER JOIN categorias c ON c.id = a.categoria_id
            WHERE d.reserva_id = ?
            ORDER BY c.nombre ASC, a.nombre ASC
            ");
        $st->execute([$reserva_id]);
        return $st->fetchAll();
    }

    public function get_extras(int $reserva_id): array {
        $st = $this->pdo->prepare("
            SELECT
            e.id,
            e.reserva_id,
            e.descripcion,
            e.proveedor,
            e.cantidad,
            e.precio_unitario,
            e.subtotal,
            e.entregado,
            e.devuelto,
            e.obs_entrega,
            e.obs_bodega,
            e.creado_en
            FROM reserva_extras e
            WHERE e.reserva_id = ?
            ORDER BY e.id ASC
            ");
        $st->execute([$reserva_id]);
        return $st->fetchAll();
    }

    public function add_mov(
        string $tipo,
        int $articulo_id,
        int $reserva_id,
        int $cantidad,
        string $nota,
        int $user_id
    ): void {
        $st = $this->pdo->prepare("
            INSERT INTO movimientos_inventario (
            tipo,
            articulo_id,
            referencia_tipo,
            referencia_id,
            cantidad,
            nota,
            creado_por
            )
            VALUES (?, ?, 'RESERVA', ?, ?, ?, ?)
            ");
        $st->execute([
                $tipo,
                $articulo_id,
                $reserva_id,
                $cantidad,
                $nota,
                $user_id
        ]);
    }

    public function set_estado(int $reserva_id, string $estado): void {
        $st = $this->pdo->prepare("
            UPDATE reservas
            SET estado = ?
            WHERE id = ?
            ");
        $st->execute([
                $estado,
                $reserva_id
        ]);
    }

    public function get_articulos_activos(): array {
        $st = $this->pdo->query("
            SELECT
            a.id,
            a.codigo,
            a.nombre,
            a.cantidad_activa,
            c.nombre AS categoria
            FROM articulos a
            INNER JOIN categorias c ON c.id = a.categoria_id
            WHERE a.estado = 'ACTIVO'
            ORDER BY c.nombre ASC, a.nombre ASC
            ");
        return $st->fetchAll();
    }

    public function entregar_update_reserva_detalle(array $params): PDOStatement {
        return $this->statement("
            UPDATE reserva_detalle
            SET entregado = entregado + ?
            WHERE id = ?
            AND reserva_id = ?
            ", $params);
    }

    public function entregar_update_reserva_extras(array $params): PDOStatement {
        return $this->statement("
            UPDATE reserva_extras
            SET entregado = entregado + ?
            WHERE id = ?
            AND reserva_id = ?
            ", $params);
    }

    public function entregar_exec(): int|false {
        return $this->pdo->exec("SET innodb_lock_wait_timeout = 5");
    }

    public function devolver_update_reserva_detalle(array $params): PDOStatement {
        return $this->statement("
            UPDATE reserva_detalle
            SET
            devuelto = devuelto + ?,
            danado = danado + ?,
            perdido = perdido + ?
            WHERE id = ?
            AND reserva_id = ?
            ", $params);
    }

    public function devolver_update_articulos(array $params): PDOStatement {
        return $this->statement("
            UPDATE articulos
            SET cantidad_activa =
            GREATEST(cantidad_activa - ?, 0)
            WHERE id = ?
            ", $params);
    }

    public function devolver_update_articulos_2(array $params): PDOStatement {
        return $this->statement("
            UPDATE articulos
            SET
            cantidad_total =
            GREATEST(cantidad_total - ?, 0),
            cantidad_activa =
            GREATEST(cantidad_activa - ?, 0)
            WHERE id = ?
            ", $params);
    }

    public function devolver_update_reserva_extras(array $params): PDOStatement {
        return $this->statement("
            UPDATE reserva_extras
            SET devuelto = devuelto + ?
            WHERE id = ?
            AND reserva_id = ?
            ", $params);
    }

    public function devolver_update_reserva_extras_2(array $params): PDOStatement {
        return $this->statement("
            UPDATE reserva_extras
            SET obs_bodega = ?
            WHERE id = ?
            AND reserva_id = ?
            ", $params);
    }

    public function devolver_exec(): int|false {
        return $this->pdo->exec("SET innodb_lock_wait_timeout = 5");
    }

    public function editar_item_pedido_select_reserva_detalle(array $params): PDOStatement {
        return $this->statement("
            SELECT d.*, a.codigo, a.nombre, a.cantidad_activa
            FROM reserva_detalle d
            INNER JOIN articulos a ON a.id = d.articulo_id
            WHERE d.id = ? AND d.reserva_id = ?
            FOR UPDATE
            ", $params);
    }

    public function editar_item_pedido_update_reserva_detalle(array $params): PDOStatement {
        return $this->statement("
            UPDATE reserva_detalle
            SET cantidad = ?
            WHERE id = ? AND reserva_id = ?
            ", $params);
    }

    public function editar_item_pedido_exec(): int|false {
        return $this->pdo->exec("SET innodb_lock_wait_timeout = 5");
    }

    public function eliminar_item_pedido_select_reserva_detalle(array $params): PDOStatement {
        return $this->statement("
            SELECT *
            FROM reserva_detalle
            WHERE id = ? AND reserva_id = ?
            FOR UPDATE
            ", $params);
    }

    public function eliminar_item_pedido_delete_from_reserva_detalle(array $params): PDOStatement {
        return $this->statement("
            DELETE FROM reserva_detalle
            WHERE id = ? AND reserva_id = ?
            ", $params);
    }

    public function eliminar_item_pedido_exec(): int|false {
        return $this->pdo->exec("SET innodb_lock_wait_timeout = 5");
    }

    public function editar_extra_pedido_select_reserva_extras(array $params): PDOStatement {
        return $this->statement("
            SELECT *
            FROM reserva_extras
            WHERE id = ? AND reserva_id = ?
            FOR UPDATE
            ", $params);
    }

    public function editar_extra_pedido_update_reserva_extras(array $params): PDOStatement {
        return $this->statement("
            UPDATE reserva_extras
            SET cantidad = ?, subtotal = ?
            WHERE id = ? AND reserva_id = ?
            ", $params);
    }

    public function editar_extra_pedido_exec(): int|false {
        return $this->pdo->exec("SET innodb_lock_wait_timeout = 5");
    }

    public function eliminar_extra_pedido_select_reserva_extras(array $params): PDOStatement {
        return $this->statement("
            SELECT *
            FROM reserva_extras
            WHERE id = ? AND reserva_id = ?
            FOR UPDATE
            ", $params);
    }

    public function eliminar_extra_pedido_delete_from_reserva_extras(array $params): PDOStatement {
        return $this->statement("
            DELETE FROM reserva_extras
            WHERE id = ? AND reserva_id = ?
            ", $params);
    }

    public function eliminar_extra_pedido_exec(): int|false {
        return $this->pdo->exec("SET innodb_lock_wait_timeout = 5");
    }

    public function entrega_adicional_select_articulos(array $params): PDOStatement {
        return $this->statement("
            SELECT
            id,
            codigo,
            nombre,
            cantidad_activa
            FROM articulos
            WHERE id = ?
            AND estado = 'ACTIVO'
            FOR UPDATE
            ", $params);
    }

    public function entrega_adicional_select_reserva_detalle(array $params): PDOStatement {
        return $this->statement("
            SELECT id, cantidad
            FROM reserva_detalle
            WHERE reserva_id = ?
            AND articulo_id = ?
            LIMIT 1
            ", $params);
    }

    public function entrega_adicional_update_reserva_detalle(array $params): PDOStatement {
        return $this->statement("
            UPDATE reserva_detalle
            SET cantidad = cantidad + ?
            WHERE id = ?
            AND reserva_id = ?
            ", $params);
    }

    public function entrega_adicional_insert_into_reserva_detalle(array $params): PDOStatement {
        return $this->statement("
            INSERT INTO reserva_detalle (
            reserva_id,
            articulo_id,
            cantidad,
            entregado,
            devuelto,
            danado,
            perdido
            )
            VALUES (?, ?, ?, 0, 0, 0, 0)
            ", $params);
    }

    public function entrega_adicional_insert_into_reserva_extras(array $params): PDOStatement {
        return $this->statement("
            INSERT INTO reserva_extras (
            reserva_id,
            descripcion,
            proveedor,
            cantidad,
            precio_unitario,
            subtotal,
            entregado,
            devuelto,
            obs_entrega,
            obs_bodega
            )
            VALUES (?, ?, ?, ?, ?, ?, 0, 0, NULL, NULL)
            ", $params);
    }

    public function entrega_adicional_exec(): int|false {
        return $this->pdo->exec("SET innodb_lock_wait_timeout = 5");
    }
}
