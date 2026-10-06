<?php
declare(strict_types=1);
namespace Marestu\Repositories;

class NotaEntregaRepository extends Repository {
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
            a.codigo, a.nombre,
            c.nombre AS categoria
            FROM reserva_detalle d
            INNER JOIN articulos a ON a.id=d.articulo_id
            INNER JOIN categorias c ON c.id=a.categoria_id
            WHERE d.reserva_id=?
            ORDER BY c.nombre ASC, a.nombre ASC");
        $st->execute([$reserva_id]);
        return $st->fetchAll();
    }

    public function reserva_extras(int $reserva_id): array {
        $st = $this->pdo->prepare("SELECT id, descripcion, proveedor, cantidad
            FROM reserva_extras
            WHERE reserva_id=?
            ORDER BY id ASC");
        $st->execute([$reserva_id]);
        return $st->fetchAll();
    }
}
