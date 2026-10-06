<?php
declare(strict_types=1);
namespace Marestu\Repositories;

final class DocumentoRepository extends Repository {
    public function reservation(int $id): array|false {
        return $this->statement('SELECT r.*, c.nombres, c.apellidos, c.telefono, c.direccion
            FROM reservas r INNER JOIN clientes c ON c.id = r.cliente_id WHERE r.id = ?', [$id])->fetch();
    }

    public function items(int $id): array {
        return $this->statement('SELECT a.*, d.*, COALESCE(d.precio_unitario, a.precio_unitario) AS precio_unitario
            FROM reserva_detalle d INNER JOIN articulos a ON a.id = d.articulo_id
            WHERE d.reserva_id = ?', [$id])->fetchAll();
    }

    public function extras(int $id): array {
        return $this->statement('SELECT * FROM reserva_extras WHERE reserva_id = ?', [$id])->fetchAll();
    }
}
