<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<link rel="stylesheet" href="assets/css/pages/index.css">
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
<script type="application/json" id="index-data"><?= json_encode(['value0' => $chart_estado_labels, 'value1' => $chart_estado_values, 'value2' => $chart_top_labels, 'value3' => $chart_top_values], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="assets/js/pages/index.js"></script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
