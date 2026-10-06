<?php
declare(strict_types=1);
namespace Marestu\Repositories;

class KardexRepository extends Repository {
    public function fetch_all(string $sql, array $params = []): array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function fetch_one(string $sql, array $params = []): array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row ?: [];
    }

    public function report(array $query): array {
        $tipos_validos = ['RESERVA','SALIDA','DEVOLUCION','AJUSTE'];
        // filtros
        $categoria_id = (int)($query['categoria_id'] ?? 0);
        $articulo_id = (int)($query['articulo_id'] ?? 0);
        $tipo = trim((string)($query['tipo'] ?? ''));
        $desde = trim((string)($query['desde'] ?? ''));
        $hasta = trim((string)($query['hasta'] ?? ''));
        $q = trim((string)($query['q'] ?? ''));
        $page = max(1, min(999999, (int)($query['page'] ?? 1)));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        // saneo filtros
        if ($tipo !== '' && !in_array($tipo, $tipos_validos, true)) $tipo = '';
        if ($desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = '';
        if ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = '';
        // categorías para filtro
        $categorias = $this->fetch_all("SELECT id, nombre
            FROM categorias
            ORDER BY nombre ASC"
        );
        // lista artículos para filtro (select)
        $articulos = $this->fetch_all("SELECT a.id, a.codigo, a.nombre, a.categoria_id, c.nombre AS categoria
            FROM articulos a
            INNER JOIN categorias c ON c.id = a.categoria_id
            WHERE a.estado='ACTIVO'
            ORDER BY c.nombre ASC, a.nombre ASC"
        );
        // construir WHERE dinámico
        $where = [];
        $params = [];
        if ($categoria_id > 0) {
            $where[] = "a.categoria_id = ?";
            $params[] = $categoria_id;
        }
        if ($articulo_id > 0) {
            $where[] = "m.articulo_id = ?";
            $params[] = $articulo_id;
        }
        if ($tipo !== '') {
            $where[] = "m.tipo = ?";
            $params[] = $tipo;
        }
        if ($desde !== '') {
            $where[] = "DATE(m.creado_en) >= ?";
            $params[] = $desde;
        }
        if ($hasta !== '') {
            $where[] = "DATE(m.creado_en) <= ?";
            $params[] = $hasta;
        }
        if ($q !== '') {
            $where[] = "(a.codigo LIKE ? OR a.nombre LIKE ? OR c.nombre LIKE ? OR m.nota LIKE ? OR u.usuario LIKE ? OR u.nombre LIKE ? OR r.codigo LIKE ?)";
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }
        $where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";
        // contar total
        $count_row = $this->fetch_one("SELECT COUNT(*) AS n
           FROM movimientos_inventario m
           INNER JOIN articulos a ON a.id = m.articulo_id
           INNER JOIN categorias c ON c.id = a.categoria_id
           LEFT JOIN usuarios u ON u.id = m.creado_por
           LEFT JOIN reservas r ON (m.referencia_tipo='RESERVA' AND r.id = m.referencia_id)
           $where_sql",
            $params
        );
        $total_rows = (int)($count_row['n'] ?? 0);
        $total_pages = max(1, (int)ceil($total_rows / $per_page));
        if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }
        // traer página
        $sql = "
        SELECT
          m.id,
          m.creado_en,
          m.tipo,
          m.cantidad,
          m.nota,
          m.referencia_tipo,
          m.referencia_id,

          a.codigo AS articulo_codigo,
          a.nombre AS articulo_nombre,
          c.nombre AS categoria_nombre,

          COALESCE(u.nombre, '') AS usuario_nombre,
          COALESCE(u.usuario, '') AS usuario_user,

          COALESCE(r.codigo, '') AS reserva_codigo

        FROM movimientos_inventario m
        INNER JOIN articulos a ON a.id = m.articulo_id
        INNER JOIN categorias c ON c.id = a.categoria_id
        LEFT JOIN usuarios u ON u.id = m.creado_por
        LEFT JOIN reservas r ON (m.referencia_tipo='RESERVA' AND r.id = m.referencia_id)

        $where_sql
        ORDER BY m.creado_en DESC, m.id DESC
        LIMIT $per_page OFFSET $offset
        ";
        $rows = $this->fetch_all($sql, $params);
        // resumen rápido (solo sobre el filtro actual)
        $resumen = $this->fetch_one("SELECT
             COALESCE(SUM(CASE WHEN m.cantidad > 0 THEN m.cantidad ELSE 0 END),0) AS entradas,
             COALESCE(SUM(CASE WHEN m.cantidad < 0 THEN -m.cantidad ELSE 0 END),0) AS salidas,
             COALESCE(SUM(m.cantidad),0) AS neto
           FROM movimientos_inventario m
           INNER JOIN articulos a ON a.id = m.articulo_id
           INNER JOIN categorias c ON c.id = a.categoria_id
           LEFT JOIN usuarios u ON u.id = m.creado_por
           LEFT JOIN reservas r ON (m.referencia_tipo='RESERVA' AND r.id = m.referencia_id)
           $where_sql",
            $params
        );
        $entradas = (int)($resumen['entradas'] ?? 0);
        $salidas = (int)($resumen['salidas'] ?? 0);
        $neto = (int)($resumen['neto'] ?? 0);
        return compact('tipos_validos', 'categoria_id', 'articulo_id', 'tipo', 'desde', 'hasta', 'q', 'page', 'per_page', 'offset', 'categorias', 'articulos', 'total_rows', 'total_pages', 'rows', 'entradas', 'salidas', 'neto');
    }
}
