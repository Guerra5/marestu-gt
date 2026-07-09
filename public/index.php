<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';

start_app_session();
require_login();

$pdo = db();

function fetch_one(PDO $pdo, string $sql, array $params = []): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $row = $st->fetch();
  return $row ?: [];
}
function fetch_all(PDO $pdo, string $sql, array $params = []): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll();
}

$today = date('Y-m-d');
$in7 = date('Y-m-d', strtotime('+7 days'));
$in14 = date('Y-m-d', strtotime('+14 days'));

// --- KPIs principales ---
$kpi_reservas_hoy = (int)(fetch_one($pdo,
  "SELECT COUNT(*) AS n
   FROM reservas
   WHERE estado IN ('CONFIRMADA','ENTREGADA')
     AND fecha_salida = ?",
  [$today]
)['n'] ?? 0);

$kpi_devoluciones_hoy = (int)(fetch_one($pdo,
  "SELECT COUNT(*) AS n
   FROM reservas
   WHERE estado='ENTREGADA'
     AND fecha_retorno = ?",
  [$today]
)['n'] ?? 0);

$kpi_proximas_7d = (int)(fetch_one($pdo,
  "SELECT COUNT(*) AS n
   FROM reservas
   WHERE estado IN ('CONFIRMADA','ENTREGADA')
     AND fecha_salida BETWEEN ? AND ?",
  [$today, $in7]
)['n'] ?? 0);

$kpi_entregas_pend = (int)(fetch_one($pdo,
  "SELECT COUNT(DISTINCT r.id) AS n
   FROM reservas r
   INNER JOIN reserva_detalle d ON d.reserva_id = r.id
   WHERE r.estado='CONFIRMADA'
     AND d.entregado < d.cantidad"
)['n'] ?? 0);

$kpi_devol_pend = (int)(fetch_one($pdo,
  "SELECT COUNT(DISTINCT r.id) AS n
   FROM reservas r
   INNER JOIN reserva_detalle d ON d.reserva_id = r.id
   WHERE r.estado='ENTREGADA'
     AND (d.devuelto + d.danado + d.perdido) < d.entregado"
)['n'] ?? 0);

$kpi_items_activos = (int)(fetch_one($pdo,
  "SELECT COUNT(*) AS n
   FROM articulos
   WHERE estado='ACTIVO'"
)['n'] ?? 0);

$kpi_stock_activo = (int)(fetch_one($pdo,
  "SELECT COALESCE(SUM(cantidad_activa),0) AS n
   FROM articulos
   WHERE estado='ACTIVO'"
)['n'] ?? 0);

// --- Alertas de stock (simple pero útil) ---
$stock_criticos = fetch_all($pdo,
  "SELECT a.codigo, a.nombre, c.nombre AS categoria, a.cantidad_activa
   FROM articulos a
   INNER JOIN categorias c ON c.id=a.categoria_id
   WHERE a.estado='ACTIVO'
     AND a.cantidad_activa <= 0
   ORDER BY a.nombre ASC
   LIMIT 10"
);

$stock_bajos = fetch_all($pdo,
  "SELECT a.codigo, a.nombre, c.nombre AS categoria, a.cantidad_activa
   FROM articulos a
   INNER JOIN categorias c ON c.id=a.categoria_id
   WHERE a.estado='ACTIVO'
     AND a.cantidad_activa BETWEEN 1 AND 3
   ORDER BY a.cantidad_activa ASC, a.nombre ASC
   LIMIT 10"
);

// --- Próximas reservas (14 días) ---
$prox_reservas = fetch_all($pdo,
  "SELECT r.id, r.codigo, r.fecha_salida, r.fecha_retorno, r.estado,
          CONCAT(cl.nombres,' ',cl.apellidos) AS cliente, cl.telefono
   FROM reservas r
   INNER JOIN clientes cl ON cl.id=r.cliente_id
   WHERE r.estado IN ('CONFIRMADA','ENTREGADA')
     AND r.fecha_salida BETWEEN ? AND ?
   ORDER BY r.fecha_salida ASC, r.id ASC
   LIMIT 15",
  [$today, $in14]
);

// --- Top artículos más reservados (últimos 30 días) ---
$since30 = date('Y-m-d', strtotime('-30 days'));
$top_art = fetch_all($pdo,
  "SELECT a.codigo, a.nombre, SUM(d.cantidad) AS qty
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

// --- Distribución por estado (para gráfica dona) ---
$estado_rows = fetch_all($pdo,
  "SELECT estado, COUNT(*) AS n
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

require __DIR__ . '/../app/views/layout/header.php';
require __DIR__ . '/../app/views/layout/sidebar.php';
?>

<style>
  .page-topbar{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;}
  .page-subtitle{color:#6c757d;margin-top:2px;}
  .card-soft{border-radius:14px;}
  .btn-pill{border-radius:10px;}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;}

  /* ✅ Responsive topbar (solo dashboard) */
  @media (max-width: 576px){
    .page-topbar{flex-direction:column; align-items:stretch;}
    .dash-actions{display:flex; flex-direction:column; gap:10px;}
    .dash-actions .btn{width:100%;}
  }
  @media (max-width: 992px){
    .dash-actions{flex-wrap:wrap;}
  }
</style>

<div class="page-topbar mb-3">
  <div>
    <div class="text-muted small">Inicio</div>
    <h3 class="mb-1">Dashboard</h3>
    <div class="page-subtitle">Resumen operativo (disponibilidad, entregas y reservas).</div>
  </div>

  <!-- ✅ antes: d-flex gap-2 (se rompía). Ahora con clase para responsive -->
  <div class="d-flex gap-2 dash-actions">
    <a href="index.php" class="btn btn-outline-secondary btn-pill">Refrescar</a>
    <a href="reservas.php" class="btn btn-outline-primary btn-pill">Ir a Reservas</a>
    <a href="calendario.php" class="btn btn-outline-dark btn-pill">Calendario</a>
  </div>
</div>

<?php if ($stock_criticos): ?>
  <div class="alert alert-danger d-flex justify-content-between align-items-center">
    <div>
      ⚠️ <b>Atención:</b> <?= count($stock_criticos) ?> artículo(s) están en stock <b>CRÍTICO</b> (activo = 0).
    </div>
    <a class="btn btn-sm btn-light btn-pill" href="articulos.php">Ver artículos</a>
  </div>
<?php endif; ?>

<!-- KPIs -->
<div class="row g-3 mb-3">
  <div class="col-12 col-md-6 col-xl-3">
    <div class="card card-soft shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Salidas hoy</div>
        <div class="fs-3 fw-bold"><?= $kpi_reservas_hoy ?></div>
        <div class="small text-muted">Reservas con salida hoy</div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6 col-xl-3">
    <div class="card card-soft shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Retornos hoy</div>
        <div class="fs-3 fw-bold"><?= $kpi_devoluciones_hoy ?></div>
        <div class="small text-muted">Reservas que deberían volver hoy</div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6 col-xl-3">
    <div class="card card-soft shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Próximas 7 días</div>
        <div class="fs-3 fw-bold"><?= $kpi_proximas_7d ?></div>
        <div class="small text-muted">Salidas en los próximos 7 días</div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6 col-xl-3">
    <div class="card card-soft shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Stock activo total</div>
        <div class="fs-3 fw-bold"><?= $kpi_stock_activo ?></div>
        <div class="small text-muted"><?= $kpi_items_activos ?> artículos activos</div>
      </div>
    </div>
  </div>
</div>

<!-- Pendientes + Alertas + Próximas -->
<div class="row g-3 mb-3">
  <div class="col-12 col-xl-4">
    <div class="card card-soft shadow-sm h-100">
      <div class="card-body p-4">
        <h5 class="mb-3">Pendientes operativos</h5>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>Entregas pendientes</div>
          <span class="badge text-bg-warning"><?= $kpi_entregas_pend ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <div>Devoluciones pendientes</div>
          <span class="badge text-bg-info"><?= $kpi_devol_pend ?></span>
        </div>

        <hr>
        <div class="small text-muted">
          * Entregas pendientes = reservas CONFIRMADAS con artículos no entregados al 100%.<br>
          * Devoluciones pendientes = reservas ENTREGADAS con artículos aún sin cerrar.
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card card-soft shadow-sm h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">Alertas de stock</h5>
          <a href="articulos.php" class="btn btn-sm btn-outline-secondary btn-pill">Ver</a>
        </div>

        <?php if (!$stock_criticos && !$stock_bajos): ?>
          <div class="text-muted">Sin alertas por ahora.</div>
        <?php endif; ?>

        <?php if ($stock_criticos): ?>
          <div class="mb-3">
            <div class="fw-semibold text-danger mb-2">Críticos (activo = 0)</div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr><th>Código</th><th>Artículo</th><th class="text-end">Activo</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($stock_criticos as $a): ?>
                    <tr>
                      <td class="mono"><?= htmlspecialchars((string)$a['codigo']) ?></td>
                      <td><?= htmlspecialchars((string)$a['nombre']) ?></td>
                      <td class="text-end"><span class="badge text-bg-danger"><?= (int)$a['cantidad_activa'] ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($stock_bajos): ?>
          <div>
            <div class="fw-semibold mb-2">Bajos (1 a 3)</div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr><th>Código</th><th>Artículo</th><th class="text-end">Activo</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($stock_bajos as $a): ?>
                    <tr>
                      <td class="mono"><?= htmlspecialchars((string)$a['codigo']) ?></td>
                      <td><?= htmlspecialchars((string)$a['nombre']) ?></td>
                      <td class="text-end"><span class="badge text-bg-warning"><?= (int)$a['cantidad_activa'] ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card card-soft shadow-sm h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">Próximas reservas (14 días)</h5>
          <a href="reservas.php" class="btn btn-sm btn-outline-secondary btn-pill">Ver</a>
        </div>

        <?php if (!$prox_reservas): ?>
          <div class="text-muted">No hay reservas próximas.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Código</th>
                  <th>Cliente</th>
                  <th>Salida</th>
                  <th class="text-end">Acción</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($prox_reservas as $r): ?>
                  <tr>
                    <td class="mono"><?= htmlspecialchars((string)$r['codigo']) ?></td>
                    <td>
                      <div class="fw-semibold"><?= htmlspecialchars((string)$r['cliente']) ?></div>
                      <div class="text-muted small"><?= htmlspecialchars((string)$r['telefono']) ?></div>
                    </td>
                    <td><?= htmlspecialchars((string)$r['fecha_salida']) ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-dark btn-pill" href="reserva_operacion.php?id=<?= (int)$r['id'] ?>">Operación</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<!-- Gráficas -->
<div class="row g-3">
  <div class="col-12 col-xl-4">
    <div class="card card-soft shadow-sm">
      <div class="card-body p-4">
        <h5 class="mb-3">Reservas por estado (60 días)</h5>
        <canvas id="chartEstados" height="220"></canvas>
        <div class="small text-muted mt-2">
          * Útil para ver cuántas están en CONFIRMADA/ENTREGADA vs cerradas.
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-8">
    <div class="card card-soft shadow-sm">
      <div class="card-body p-4">
        <h5 class="mb-3">Top artículos reservados (30 días)</h5>
        <canvas id="chartTop" height="220"></canvas>
        <div class="small text-muted mt-2">
          * Ayuda a decidir qué comprar/poner en alerta por demanda.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const estadoLabels = <?= json_encode($chart_estado_labels, JSON_UNESCAPED_UNICODE) ?>;
  const estadoValues = <?= json_encode($chart_estado_values, JSON_UNESCAPED_UNICODE) ?>;

  const topLabels = <?= json_encode($chart_top_labels, JSON_UNESCAPED_UNICODE) ?>;
  const topValues = <?= json_encode($chart_top_values, JSON_UNESCAPED_UNICODE) ?>;

  const ctx1 = document.getElementById('chartEstados');
  new Chart(ctx1, {
    type: 'doughnut',
    data: {
      labels: estadoLabels,
      datasets: [{ data: estadoValues }]
    },
    options: {
      plugins: { legend: { position: 'bottom' } }
    }
  });

  const ctx2 = document.getElementById('chartTop');
  new Chart(ctx2, {
    type: 'bar',
    data: {
      labels: topLabels,
      datasets: [{ data: topValues }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
</script>

<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>
