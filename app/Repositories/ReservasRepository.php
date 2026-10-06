<?php
declare(strict_types=1);
namespace Marestu\Repositories;

use PDOStatement;

class ReservasRepository extends Repository {
    public function next_res_code(): string {
        $st = $this->pdo->query("SELECT id FROM reservas ORDER BY id DESC LIMIT 1");
        $row = $st->fetch();
        $next = $row ? ((int)$row['id'] + 1) : 1;
        return 'RES-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
    }

    public function get_active_clients(): array {
        $st = $this->pdo->query("
            SELECT id, nombres, apellidos, telefono
            FROM clientes
            WHERE estado = 'ACTIVO'
            ORDER BY nombres ASC, apellidos ASC
            ");
        return $st->fetchAll();
    }

    public function listing(array $query): array {
        $q = trim((string)($query['q'] ?? ''));
        $estado_filtro = (string)($query['estado'] ?? '');
        $sql = "
        SELECT
        r.id,
        r.codigo,
        r.fecha_salida,
        r.fecha_evento,
        r.fecha_retorno,
        r.estado,
        r.creado_en,
        CONCAT(c.nombres, ' ', c.apellidos) AS cliente,
        c.telefono,
        COALESCE(
        SUM(GREATEST(d.cantidad - d.entregado, 0)),
        0
        ) AS pend_entrega,
        COALESCE(
        SUM(
        GREATEST(
        d.entregado - (d.devuelto + d.danado + d.perdido),
        0
        )
        ),
        0
        ) AS pend_devol
        FROM reservas r
        INNER JOIN clientes c
        ON c.id = r.cliente_id
        LEFT JOIN reserva_detalle d
        ON d.reserva_id = r.id
        WHERE 1 = 1
        ";
        $params = [];
        if ($q !== '') {
            $sql .= "
            AND (
              r.codigo LIKE ?
              OR c.nombres LIKE ?
              OR c.apellidos LIKE ?
              OR c.telefono LIKE ?
            )
          ";
            $search = "%{$q}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        $estados_validos = [
            'BORRADOR',
            'CONFIRMADA',
            'ENTREGADA',
            'DEVUELTA',
            'CANCELADA'
        ];
        if (in_array($estado_filtro, $estados_validos, true)) {
            $sql .= " AND r.estado = ? ";
            $params[] = $estado_filtro;
        }
        $sql .= "
          GROUP BY
            r.id,
            r.codigo,
            r.fecha_salida,
            r.fecha_evento,
            r.fecha_retorno,
            r.estado,
            r.creado_en,
            c.nombres,
            c.apellidos,
            c.telefono
          ORDER BY r.id DESC
        ";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        return compact('q', 'estado_filtro', 'estados_validos', 'rows');
    }

    public function save_select_clientes(array $params): PDOStatement {
        return $this->statement("
            SELECT id
            FROM clientes
            WHERE id = ?
            AND estado = 'ACTIVO'
            LIMIT 1
            ", $params);
    }

    public function save_insert_into_reservas(array $params): PDOStatement {
        return $this->statement("
            INSERT INTO reservas (
            codigo,
            cliente_id,
            fecha_salida,
            fecha_evento,
            fecha_retorno,
            estado,
            nota,
            creado_por,
            direccion_evento
            )
            VALUES (?, ?, ?, ?, ?, 'BORRADOR', ?, ?, ?)
            ", $params);
    }
}
