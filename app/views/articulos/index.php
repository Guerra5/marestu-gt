<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<link rel="stylesheet" href="assets/css/pages/articulos.css">
<div class="page-topbar mb-3">
  <div>
    <div class="text-muted small">Catálogo</div>
    <h3 class="mb-1">Artículos</h3>
    <div class="page-subtitle">Crear, editar, ajustar stock y mantener kardex.</div>
  </div>
  <div>
    <a href="articulos.php" class="btn btn-outline-secondary btn-pill">Refrescar</a>
  </div>
</div>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<!-- CREAR -->
<div class="card card-soft shadow-sm mb-4">
  <div class="card-body p-4">
    <h5 class="mb-3">Crear artículo</h5>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="row g-3">
        <div class="col-12 col-lg-5">
          <label class="form-label">Nombre</label>
          <input class="form-control" name="nombre" required placeholder="Ej: Silla Tiffany, Mesa redonda 1.5m...">
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Categoría</label>
          <select class="form-select" name="categoria_id" required>
            <option value="">-- Seleccionar --</option>
            <?php foreach ($cats_active as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars((string)$c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label">Unidad</label>
          <input class="form-control" name="unidad" value="pza" required>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label">Precio (Q)</label>
          <input type="number" step="0.01" min="0" class="form-control" name="precio_unitario" value="0.00" required>
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label">Ubicación</label>
          <input class="form-control" name="ubicacion" placeholder="Bodega A">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label">Cant. total</label>
          <input type="number" min="0" class="form-control" name="cantidad_total" value="0" required>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label">Cant. activa</label>
          <input type="number" min="0" class="form-control" name="cantidad_activa" value="0" required>
        </div>
        <div class="col-12 col-lg-8">
          <label class="form-label">Observaciones</label>
          <input class="form-control" name="observaciones" placeholder="Opcional">
        </div>
        <div class="col-12">
          <div class="small text-muted">
            * El código se genera automáticamente (ART-000001).<br>
            * Se registra ENTRADA en kardex si la cantidad activa inicial es mayor a 0.
          </div>
        </div>
        <div class="col-12">
          <button class="btn btn-primary btn-pill">Crear artículo</button>
        </div>
      </div>
    </form>
  </div>
</div>
<!-- EDITAR (si viene ?edit=ID) -->
<?php if ($edit_row): ?>
  <div class="card card-soft shadow-sm mb-4 border border-warning">
    <div class="card-body p-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
          <h5 class="mb-0">Editar artículo <span class="mono"><?= htmlspecialchars((string)$edit_row['codigo']) ?></span></h5>
          <div class="text-muted small">
            <?= htmlspecialchars((string)$edit_row['categoria_nombre']) ?>
            <?php
              if ((int)$edit_row['categoria_activa'] === 0) echo ' (Inactiva)';
            ?>
          </div>
        </div>
        <a href="articulos.php" class="btn btn-outline-secondary btn-pill btn-sm">Cancelar edición</a>
      </div>
      <div class="row g-3">
        <!-- Datos generales -->
        <div class="col-12 col-lg-8">
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$edit_row['id'] ?>">
            <div class="col-12 col-lg-6">
              <label class="form-label">Nombre</label>
              <input class="form-control" name="nombre" required value="<?= htmlspecialchars((string)$edit_row['nombre']) ?>">
            </div>
            <div class="col-12 col-lg-3">
              <label class="form-label">Categoría</label>
              <select class="form-select" name="categoria_id" required>
                <?php foreach ($cats_all as $c): ?>
                  <?php
                    $isSelected = ((int)$c['id'] === (int)$edit_row['categoria_id']);
                    $isInactive = ((int)$c['activo'] === 0);
                    // Si está inactiva y NO es la seleccionada, la deshabilitamos
                    $disabled = ($isInactive && !$isSelected) ? 'disabled' : '';
                    $label = (string)$c['nombre'] . ($isInactive ? ' (Inactiva)' : '');
                  ?>
                  <option value="<?= (int)$c['id'] ?>" <?= $isSelected ? 'selected' : '' ?> <?= $disabled ?>>
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="small text-muted mt-1">
                * No podés cambiar a categorías inactivas.
              </div>
            </div>
            <div class="col-12 col-lg-3">
              <label class="form-label">Estado</label>
              <select class="form-select" name="estado">
                <option value="ACTIVO" <?= ((string)$edit_row['estado']==='ACTIVO')?'selected':'' ?>>ACTIVO</option>
                <option value="INACTIVO" <?= ((string)$edit_row['estado']==='INACTIVO')?'selected':'' ?>>INACTIVO</option>
              </select>
            </div>
            <div class="col-6 col-lg-3">
              <label class="form-label">Unidad</label>
              <input class="form-control" name="unidad" required value="<?= htmlspecialchars((string)$edit_row['unidad']) ?>">
            </div>
            <div class="col-6 col-lg-3">
              <label class="form-label">Precio (Q)</label>
              <input
                type="number"
                name="precio_unitario"
                class="form-control"
                step="0.01"
                min="0"
                inputmode="decimal"
                required
                value="<?= htmlspecialchars((string)number_format((float)($edit_row['precio_unitario'] ?? 0), 2, '.', '')) ?>"
              >
            </div>
            <div class="col-6 col-lg-3">
              <label class="form-label">Ubicación</label>
              <input class="form-control" name="ubicacion" value="<?= htmlspecialchars((string)($edit_row['ubicacion'] ?? '')) ?>">
            </div>
            <div class="col-12 col-lg-9">
              <label class="form-label">Observaciones</label>
              <input class="form-control" name="observaciones" value="<?= htmlspecialchars((string)($edit_row['observaciones'] ?? '')) ?>">
            </div>
            <div class="col-12">
              <button class="btn btn-warning btn-pill">Guardar datos</button>
            </div>
          </form>
        </div>
        <!-- Ajuste de stock -->
        <div class="col-12 col-lg-4">
          <div class="p-3 bg-light rounded-3 border">
            <h6 class="mb-2">Ajuste de stock</h6>
            <div class="small text-muted mb-2">
              Esto actualiza cantidades y registra un movimiento <b>AJUSTE</b> en kardex si cambia el stock activo.
            </div>
            <form method="post" class="row g-2">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="stock">
              <input type="hidden" name="id" value="<?= (int)$edit_row['id'] ?>">
              <div class="col-6">
                <label class="form-label">Total</label>
                <input type="number" min="0" class="form-control" name="cantidad_total"
                       value="<?= (int)$edit_row['cantidad_total'] ?>" required>
              </div>
              <div class="col-6">
                <label class="form-label">Activo</label>
                <input type="number" min="0" class="form-control" name="cantidad_activa"
                       value="<?= (int)$edit_row['cantidad_activa'] ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label">Nota (opcional)</label>
                <input class="form-control" name="nota" placeholder="Ej: 5 dañadas, se dieron de baja...">
              </div>
              <div class="col-12">
                <button class="btn btn-outline-dark btn-pill w-100">Aplicar ajuste</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<!-- LISTADO -->
<div class="card card-soft shadow-sm">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0">Listado</h5>
      <form class="d-flex gap-2" method="get">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Buscar por nombre o código...">
        <select name="cat" class="form-select" data-style="articulos-1">
          <option value="0">Todas las categorías</option>
          <?php foreach ($cats_all as $c): ?>
            <?php $label = (string)$c['nombre'] . (((int)$c['activo']===0) ? ' (Inactiva)' : ''); ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($cat===(int)$c['id'])?'selected':'' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="estado" class="form-select" data-style="articulos-2">
          <option value="" <?= ($estado_f==='')?'selected':'' ?>>Todos</option>
          <option value="ACTIVO" <?= ($estado_f==='ACTIVO')?'selected':'' ?>>Activos</option>
          <option value="INACTIVO" <?= ($estado_f==='INACTIVO')?'selected':'' ?>>Inactivos</option>
        </select>
        <button class="btn btn-outline-primary btn-pill">Filtrar</button>
        <a href="articulos.php" class="btn btn-outline-secondary btn-pill">Limpiar</a>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr>
            <th data-style="articulos-3">#</th>
            <th>Código</th>
            <th>Artículo</th>
            <th>Categoría</th>
            <th>Precio</th>
            <th>Stock</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">Sin artículos.</td></tr>
          <?php endif; ?>
          <?php $n=0; foreach ($rows as $r): $n++; ?>
            <?php
              $isActive = ((string)$r['estado'] === 'ACTIVO');
              $total = (int)$r['cantidad_total'];
              $act = (int)$r['cantidad_activa'];
              $precio = (float)($r['precio_unitario'] ?? 0);
            ?>
            <tr>
              <td><?= $n ?></td>
              <td class="mono"><?= htmlspecialchars((string)$r['codigo']) ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string)$r['nombre']) ?></div>
                <div class="text-muted small">Unidad: <?= htmlspecialchars((string)$r['unidad']) ?></div>
              </td>
              <td><?= htmlspecialchars((string)$r['categoria']) ?></td>
              <td>Q <?= number_format($precio, 2) ?></td>
              <td>
                <span class="badge text-bg-dark">Total: <?= $total ?></span>
                <span class="badge text-bg-success">Activo: <?= $act ?></span>
              </td>
              <td>
                <?php if ($isActive): ?>
                  <span class="badge text-bg-success">ACTIVO</span>
                <?php else: ?>
                  <span class="badge text-bg-danger">INACTIVO</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <a href="articulos.php?edit=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary btn-pill">Editar</a>
                  <form method="post" action="articulos.php" class="m-0" data-confirm="¿Seguro?">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-pill">
                      <?= $isActive ? 'Desactivar' : 'Activar' ?>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="small text-muted mt-3">
      * No borramos artículos: se desactivan para mantener historial.<br>
      * El kardex registra ENTRADA inicial y AJUSTES de stock activo.
    </div>
  </div>
</div>
<?php
require __DIR__ . '/../layout/footer.php';
