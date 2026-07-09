<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';

start_app_session();
require_login();

$pdo = db();

function fetch_all(PDO $pdo, string $sql, array $params = []): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}
function fetch_one(PDO $pdo, string $sql, array $params = []): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $row = $st->fetch();
  return $row ?: [];
}

function clamp_int(int $v, int $min, int $max): int {
  return max($min, min($max, $v));
}

$tipos_validos = ['RESERVA','SALIDA','DEVOLUCION','AJUSTE'];

// filtros
$articulo_id = (int)($_GET['articulo_id'] ?? 0);
$tipo = trim((string)($_GET['tipo'] ?? ''));
$desde = trim((string)($_GET['desde'] ?? ''));
$hasta = trim((string)($_GET['hasta'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$page = clamp_int((int)($_GET['page'] ?? 1), 1, 999999);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// saneo filtros
if ($tipo !== '' && !in_array($tipo, $tipos_validos, true)) $tipo = '';

if ($desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = '';
if ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = '';

// lista artículos para filtro (select)
$articulos = fetch_all($pdo,
  "SELECT id, codigo, nombre
   FROM articulos
   WHERE estado='ACTIVO'
   ORDER BY nombre ASC"
);

// construir WHERE dinámico
$where = [];
$params = [];

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
  $where[] = "(a.codigo LIKE ? OR a.nombre LIKE ? OR m.nota LIKE ? OR u.usuario LIKE ? OR u.nombre LIKE ? OR r.codigo LIKE ?)";
  $like = '%' . $q . '%';
  array_push($params, $like, $like, $like, $like, $like, $like);
}

$where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// contar total
$count_row = fetch_one($pdo,
  "SELECT COUNT(*) AS n
   FROM movimientos_inventario m
   INNER JOIN articulos a ON a.id = m.articulo_id
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

  COALESCE(u.nombre, '') AS usuario_nombre,
  COALESCE(u.usuario, '') AS usuario_user,

  COALESCE(r.codigo, '') AS reserva_codigo

FROM movimientos_inventario m
INNER JOIN articulos a ON a.id = m.articulo_id
LEFT JOIN usuarios u ON u.id = m.creado_por
LEFT JOIN reservas r ON (m.referencia_tipo='RESERVA' AND r.id = m.referencia_id)

$where_sql
ORDER BY m.creado_en DESC, m.id DESC
LIMIT $per_page OFFSET $offset
";

$rows = fetch_all($pdo, $sql, $params);

// resumen rápido (solo sobre el filtro actual)
$resumen = fetch_one($pdo,
  "SELECT
     COALESCE(SUM(CASE WHEN m.cantidad > 0 THEN m.cantidad ELSE 0 END),0) AS entradas,
     COALESCE(SUM(CASE WHEN m.cantidad < 0 THEN -m.cantidad ELSE 0 END),0) AS salidas,
     COALESCE(SUM(m.cantidad),0) AS neto
   FROM movimientos_inventario m
   INNER JOIN articulos a ON a.id = m.articulo_id
   LEFT JOIN usuarios u ON u.id = m.creado_por
   LEFT JOIN reservas r ON (m.referencia_tipo='RESERVA' AND r.id = m.referencia_id)
   $where_sql",
  $params
);

$entradas = (int)($resumen['entradas'] ?? 0);
$salidas = (int)($resumen['salidas'] ?? 0);
$neto = (int)($resumen['neto'] ?? 0);

require __DIR__ . '/../app/views/layout/header.php';
require __DIR__ . '/../app/views/layout/sidebar.php';
?>

<style>
  .card-soft{border-radius:14px;}
  .btn-pill{border-radius:999px;}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;}
</style>

<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <div class="text-muted small">Inventario</div>
    <h3 class="mb-1">Kardex</h3>
    <div class="text-muted">Historial de movimientos (solo lectura).</div>
  </div>
  <div class="d-flex gap-2">
    <a href="kardex.php" class="btn btn-outline-secondary btn-pill">Refrescar</a>
    <a href="articulos.php" class="btn btn-outline-primary btn-pill">Artículos</a>
  </div>
</div>

<!-- Resumen -->
<div class="row g-3 mb-3">
  <div class="col-12 col-md-4">
    <div class="card card-soft shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Entradas (+)</div>
        <div class="fs-3 fw-bold"><?= $entradas ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="card card-soft shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Salidas (-)</div>
        <div class="fs-3 fw-bold"><?= $salidas ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="card card-soft shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Neto</div>
        <div class="fs-3 fw-bold"><?= $neto ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Filtros -->
<div class="card card-soft shadow-sm mb-3">
  <div class="card-body p-4">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-12 col-md-4">
        <label class="form-label">Artículo</label>
        <select name="articulo_id" class="form-select">
          <option value="0">Todos</option>
          <?php foreach ($articulos as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $articulo_id===(int)$a['id']?'selected':'' ?>>
              <?= htmlspecialchars((string)$a['codigo']) ?> — <?= htmlspecialchars((string)$a['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select">
          <option value="">Todos</option>
          <?php foreach ($tipos_validos as $t): ?>
            <option value="<?= $t ?>" <?= $tipo===$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label">Desde</label>
        <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($desde) ?>">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label">Hasta</label>
        <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($hasta) ?>">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label">Buscar</label>
        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="código, nota, usuario...">
      </div>

      <div class="col-12 d-flex gap-2 mt-2">
        <button class="btn btn-primary btn-pill">Aplicar</button>
        <a class="btn btn-outline-secondary btn-pill" href="kardex.php">Limpiar</a>
      </div>
    </form>
  </div>
</div>

<!-- Tabla -->
<div class="card card-soft shadow-sm">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="text-muted">
        Mostrando <b><?= count($rows) ?></b> de <b><?= $total_rows ?></b> movimientos
      </div>
      <div class="text-muted small">Página <?= $page ?> / <?= $total_pages ?></div>
    </div>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr>
            <th>Fecha</th>
            <th>Artículo</th>
            <th>Tipo</th>
            <th class="text-end">Cantidad</th>
            <th>Referencia</th>
            <th>Usuario</th>
            <th>Nota</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-muted">Sin movimientos con esos filtros.</td></tr>
          <?php endif; ?>

          <?php foreach ($rows as $r): ?>
            <?php
              $qty = (int)$r['cantidad'];
              $qty_class = $qty > 0 ? 'text-success' : ($qty < 0 ? 'text-danger' : 'text-muted');
              $tipoBadge = match ((string)$r['tipo']) {
                'SALIDA' => 'text-bg-danger',
                'DEVOLUCION' => 'text-bg-success',
                'AJUSTE' => 'text-bg-warning',
                'RESERVA' => 'text-bg-secondary',
                default => 'text-bg-dark',
              };

              $ref = '';
              if ((string)$r['referencia_tipo'] === 'RESERVA' && (int)$r['referencia_id'] > 0) {
                $ref = 'Reserva ' . ((string)$r['reserva_codigo'] ?: ('#' . (int)$r['referencia_id']));
              }
            ?>
            <tr>
              <td class="text-muted">
                <?= htmlspecialchars((string)$r['creado_en']) ?>
              </td>

              <td>
                <div class="mono fw-semibold"><?= htmlspecialchars((string)$r['articulo_codigo']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars((string)$r['articulo_nombre']) ?></div>
              </td>

              <td>
                <span class="badge <?= $tipoBadge ?>"><?= htmlspecialchars((string)$r['tipo']) ?></span>
              </td>

              <td class="text-end">
                <span class="<?= $qty_class ?> fw-bold"><?= $qty > 0 ? '+' : '' ?><?= $qty ?></span>
              </td>

              <td>
                <?php if ($ref !== ''): ?>
                  <a href="reserva_editar.php?id=<?= (int)$r['referencia_id'] ?>" class="text-decoration-none">
                    <?= htmlspecialchars($ref) ?>
                  </a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ((string)$r['usuario_user'] !== ''): ?>
                  <div class="fw-semibold"><?= htmlspecialchars((string)$r['usuario_nombre']) ?></div>
                  <div class="text-muted small">@<?= htmlspecialchars((string)$r['usuario_user']) ?></div>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>

              <td class="text-muted">
                <?= htmlspecialchars((string)$r['nota']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <nav class="d-flex justify-content-end">
      <ul class="pagination mb-0">
        <?php
          // mantener filtros en paginación
          $qs = $_GET;
          $prev = max(1, $page - 1);
          $next = min($total_pages, $page + 1);

          $qs['page'] = $prev; $prevUrl = 'kardex.php?' . http_build_query($qs);
          $qs['page'] = $next; $nextUrl = 'kardex.php?' . http_build_query($qs);

          $qs['page'] = 1; $firstUrl = 'kardex.php?' . http_build_query($qs);
          $qs['page'] = $total_pages; $lastUrl = 'kardex.php?' . http_build_query($qs);
        ?>

        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="<?= htmlspecialchars($firstUrl) ?>">«</a>
        </li>
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="<?= htmlspecialchars($prevUrl) ?>">‹</a>
        </li>

        <li class="page-item disabled"><span class="page-link"><?= $page ?></span></li>

        <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
          <a class="page-link" href="<?= htmlspecialchars($nextUrl) ?>">›</a>
        </li>
        <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
          <a class="page-link" href="<?= htmlspecialchars($lastUrl) ?>">»</a>
        </li>
      </ul>
    </nav>

    <div class="small text-muted mt-3">
      * Kárdex es <b>solo lectura</b>. Los movimientos se generan desde: Reservas, Operación diaria y Ajustes.
    </div>
  </div>
</div>

<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>
