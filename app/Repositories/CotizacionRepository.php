<?php
declare(strict_types=1);
namespace Marestu\Repositories;

class CotizacionRepository extends Repository {
    public function get_reserva(int $id): ?array {
        $st = $this->pdo->prepare("SELECT r.*,
            CONCAT(c.nombres,' ',c.apellidos) AS cliente,
            c.telefono,
            c.email,
            c.direccion
            FROM reservas r
            INNER JOIN clientes c ON c.id = r.cliente_id
            WHERE r.id=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function reserva_items(int $reserva_id): array {
        $st = $this->pdo->prepare("SELECT d.id, d.cantidad,
            a.codigo, a.nombre, a.precio_unitario
            FROM reserva_detalle d
            INNER JOIN articulos a ON a.id=d.articulo_id
            WHERE d.reserva_id=?
            ORDER BY a.nombre ASC");
        $st->execute([$reserva_id]);
        return $st->fetchAll();
    }

    public function reserva_extras(int $reserva_id): array {
        $st = $this->pdo->prepare("SELECT id, descripcion, proveedor, cantidad, precio_unitario,
            (cantidad * precio_unitario) AS subtotal
            FROM reserva_extras
            WHERE reserva_id=?
            ORDER BY id ASC");
        $st->execute([$reserva_id]);
        return $st->fetchAll();
    }
}
