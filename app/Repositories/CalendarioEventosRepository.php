<?php
declare(strict_types=1);
namespace Marestu\Repositories;

final class CalendarioEventosRepository extends Repository {
    public function between(string $start, string $end, bool $openOnly): array {
        $state = $openOnly ? "r.estado IN ('CONFIRMADA','ENTREGADA')" : "r.estado <> 'CANCELADA'";
        return $this->statement("SELECT r.id, r.codigo, r.fecha_salida, r.fecha_retorno, r.estado,
            CONCAT(c.nombres,' ',c.apellidos) AS cliente, c.telefono
            FROM reservas r INNER JOIN clientes c ON c.id = r.cliente_id
            WHERE r.fecha_salida <= ? AND r.fecha_retorno >= ? AND {$state}
            ORDER BY r.fecha_salida ASC, r.id ASC", [$end, $start])->fetchAll();
    }
}
