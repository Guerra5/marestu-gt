<?php
declare(strict_types=1);
namespace Marestu\Repositories;

class DashboardRepository extends Repository {
    public function fetch_one(string $sql, array $params = []): array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row ?: [];
    }

    public function fetch_all(string $sql, array $params = []): array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function report(array $query): array {
        $today = date('Y-m-d');
        $in7 = date('Y-m-d', strtotime('+7 days'));
        $in14 = date('Y-m-d', strtotime('+14 days'));

        $kpi_reservas_hoy = (int)($this->fetch_one("SELECT COUNT(*) AS n
                FROM reservas
                WHERE estado IN ('CONFIRMADA','ENTREGADA')
                AND fecha_salida = ?",
                [$today]
            )['n'] ?? 0);
        $kpi_devoluciones_hoy = (int)($this->fetch_one("SELECT COUNT(*) AS n
                FROM reservas
                WHERE estado='ENTREGADA'
                AND fecha_retorno = ?",
                [$today]
            )['n'] ?? 0);
        $kpi_proximas_7d = (int)($this->fetch_one("SELECT COUNT(*) AS n
                FROM reservas
                WHERE estado IN ('CONFIRMADA','ENTREGADA')
                AND fecha_salida BETWEEN ? AND ?",
                [$today, $in7]
            )['n'] ?? 0);
        $kpi_entregas_pend = (int)($this->fetch_one("SELECT COUNT(DISTINCT r.id) AS n
                FROM reservas r
                INNER JOIN reserva_detalle d ON d.reserva_id = r.id
                WHERE r.estado='CONFIRMADA'
                AND d.entregado < d.cantidad"
            )['n'] ?? 0);
        $kpi_devol_pend = (int)($this->fetch_one("SELECT COUNT(DISTINCT r.id) AS n
                FROM reservas r
                INNER JOIN reserva_detalle d ON d.reserva_id = r.id
                WHERE r.estado='ENTREGADA'
                AND (d.devuelto + d.danado + d.perdido) < d.entregado"
            )['n'] ?? 0);
        $kpi_items_activos = (int)($this->fetch_one("SELECT COUNT(*) AS n
                FROM articulos
                WHERE estado='ACTIVO'"
            )['n'] ?? 0);
        $kpi_stock_activo = (int)($this->fetch_one("SELECT COALESCE(SUM(cantidad_activa),0) AS n
                FROM articulos
                WHERE estado='ACTIVO'"
            )['n'] ?? 0);

        $stock_criticos = $this->fetch_all("SELECT a.codigo, a.nombre, c.nombre AS categoria, a.cantidad_activa
            FROM articulos a
            INNER JOIN categorias c ON c.id=a.categoria_id
            WHERE a.estado='ACTIVO'
            AND a.cantidad_activa <= 0
            ORDER BY a.nombre ASC
            LIMIT 10"
        );
        $stock_bajos = $this->fetch_all("SELECT a.codigo, a.nombre, c.nombre AS categoria, a.cantidad_activa
            FROM articulos a
            INNER JOIN categorias c ON c.id=a.categoria_id
            WHERE a.estado='ACTIVO'
            AND a.cantidad_activa BETWEEN 1 AND 3
            ORDER BY a.cantidad_activa ASC, a.nombre ASC
            LIMIT 10"
        );

        $prox_reservas = $this->fetch_all("SELECT r.id, r.codigo, r.fecha_salida, r.fecha_retorno, r.estado,
            CONCAT(cl.nombres,' ',cl.apellidos) AS cliente, cl.telefono
            FROM reservas r
            INNER JOIN clientes cl ON cl.id=r.cliente_id
            WHERE r.estado IN ('CONFIRMADA','ENTREGADA')
            AND r.fecha_salida BETWEEN ? AND ?
            ORDER BY r.fecha_salida ASC, r.id ASC
            LIMIT 15",
            [$today, $in14]
        );

        $since30 = date('Y-m-d', strtotime('-30 days'));
        $top_art = $this->fetch_all("SELECT a.codigo, a.nombre, SUM(d.cantidad) AS qty
            FROM reservas r
            INNER JOIN reserva_detalle d ON d.reserva_id = r.id
            INNER JOIN articulos a ON a.id = d.articulo_id
            WHERE r.estado IN ('CONFIRMADA','ENTREGADA','DEVUELTA')
            AND r.fecha_salida >= ?
            GROUP BY a.id
            ORDER BY qty DESC
            LIMIT 7",
            [$since30]
        );

        $estado_rows = $this->fetch_all("SELECT estado, COUNT(*) AS n
            FROM reservas
            WHERE creado_en >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            GROUP BY estado
            ORDER BY n DESC"
        );
        // Preparar data para Chart.js
        $chart_estado_labels = [];
        $chart_estado_values = [];
        foreach ($estado_rows as $r) {
            $chart_estado_labels[] = (string)$r['estado'];
            $chart_estado_values[] = (int)$r['n'];
        }
        $chart_top_labels = [];
        $chart_top_values = [];
        foreach ($top_art as $r) {
            $chart_top_labels[] = (string)$r['codigo'];
            $chart_top_values[] = (int)$r['qty'];
        }
        return compact('today', 'in7', 'in14', 'kpi_reservas_hoy', 'kpi_devoluciones_hoy', 'kpi_proximas_7d', 'kpi_entregas_pend', 'kpi_devol_pend', 'kpi_items_activos', 'kpi_stock_activo', 'stock_criticos', 'stock_bajos', 'prox_reservas', 'top_art', 'chart_estado_labels', 'chart_estado_values', 'chart_top_labels', 'chart_top_values');
    }
}
