<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<link rel="stylesheet" href="assets/css/pages/kardex.css">
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
      <div class="col-12 col-md-3">
        <label class="form-label">Categoría</label>
        <select name="categoria_id" id="categoriaKardex" class="form-select">
          <option value="0">Todas</option>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $categoria_id===(int)$c['id']?'selected':'' ?>>
              <?= htmlspecialchars((string)$c['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Artículo</label>
        <select name="articulo_id" id="articuloKardex" class="form-select">
          <option value="0">Todos</option>
          <?php foreach ($articulos as $a): ?>
            <option
              value="<?= (int)$a['id'] ?>"
              data-categoria="<?= (int)$a['categoria_id'] ?>"
              <?= $articulo_id===(int)$a['id']?'selected':'' ?>
            >
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
        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="código, nombre, categoría...">
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
                <div class="text-muted small"><?= htmlspecialchars((string)$r['categoria_nombre']) ?></div>
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
          $qs = $paginationQuery;
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
<script src="assets/js/pages/kardex.js"></script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
