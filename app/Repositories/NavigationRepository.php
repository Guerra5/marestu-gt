<?php
declare(strict_types=1);
namespace Marestu\Repositories;

final class NavigationRepository extends Repository {
    public function alerts(): int {
        try {
            $delivery = $this->statement("SELECT COUNT(*) AS n FROM (
                SELECT r.id FROM reservas r
                LEFT JOIN reserva_detalle d ON d.reserva_id = r.id
                WHERE r.estado = 'CONFIRMADA'
                AND DATE(r.fecha_salida) <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                GROUP BY r.id
                HAVING SUM(GREATEST(d.cantidad - d.entregado, 0)) > 0
                ) x")->fetch();
            $returns = $this->statement("SELECT COUNT(*) AS n FROM (
                SELECT r.id FROM reservas r
                LEFT JOIN reserva_detalle d ON d.reserva_id = r.id
                WHERE r.estado = 'ENTREGADA'
                AND DATE(r.fecha_retorno) <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                GROUP BY r.id
                HAVING SUM(GREATEST(d.entregado - (d.devuelto + d.danado + d.perdido), 0)) > 0
                ) y")->fetch();
            return (int)($delivery['n'] ?? 0) + (int)($returns['n'] ?? 0);
        } catch (\Throwable $error) {
            error_log('No se pudieron cargar las alertas: ' . $error->getMessage());
            return 0;
        }
    }
}
