<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<link rel="stylesheet" href="assets/css/pages/clientes.css">
<div class="page-topbar mb-3">
  <div>
    <div class="text-muted small">Clientes</div>
    <h3 class="mb-1">Gestión de clientes</h3>
    <div class="page-subtitle"><?= can('clientes.gestionar') ? 'Crear, editar y activar/desactivar clientes.' : 'Consulta de clientes.' ?></div>
  </div>
  <div>
    <a href="clientes.php" class="btn btn-outline-secondary btn-pill">Refrescar</a>
  </div>
</div>
<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (can('clientes.gestionar')): ?>
<!-- CREAR -->
<div class="card card-soft shadow-sm mb-4">
  <div class="card-body p-4">
    <h5 class="mb-3">Crear cliente</h5>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="row g-3">
        <div class="col-12 col-lg-3">
          <label class="form-label">Nombres</label>
          <input class="form-control" name="nombres" required>
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Apellidos</label>
          <input class="form-control" name="apellidos" required>
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label">Teléfono</label>
          <input class="form-control" name="telefono" required placeholder="Ej: 5555-5555">
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label">Email</label>
          <input class="form-control" name="email" placeholder="Opcional">
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label">NIT</label>
          <input class="form-control" name="nit" placeholder="Opcional">
        </div>
        <div class="col-12 col-lg-8">
          <label class="form-label">Dirección</label>
          <input class="form-control" name="direccion" placeholder="Opcional">
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label">Estado</label>
          <select class="form-select" name="estado">
            <option value="ACTIVO" selected>ACTIVO</option>
            <option value="INACTIVO">INACTIVO</option>
          </select>
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label">Observaciones</label>
          <input class="form-control" name="observaciones" placeholder="Opcional">
        </div>
        <div class="col-12">
          <button class="btn btn-primary btn-pill">Crear cliente</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<!-- EDITAR -->
<?php if ($edit_row): ?>
  <div class="card card-soft shadow-sm mb-4 border border-warning">
    <div class="card-body p-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0">Editar cliente #<?= (int)$edit_row['id'] ?></h5>
        <a href="clientes.php" class="btn btn-outline-secondary btn-pill btn-sm">Cancelar edición</a>
      </div>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int)$edit_row['id'] ?>">
        <div class="row g-3">
          <div class="col-12 col-lg-3">
            <label class="form-label">Nombres</label>
            <input class="form-control" name="nombres" required value="<?= htmlspecialchars((string)$edit_row['nombres']) ?>">
          </div>
          <div class="col-12 col-lg-3">
            <label class="form-label">Apellidos</label>
            <input class="form-control" name="apellidos" required value="<?= htmlspecialchars((string)$edit_row['apellidos']) ?>">
          </div>
          <div class="col-12 col-lg-2">
            <label class="form-label">Teléfono</label>
            <input class="form-control" name="telefono" required value="<?= htmlspecialchars((string)$edit_row['telefono']) ?>">
          </div>
          <div class="col-12 col-lg-2">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" value="<?= htmlspecialchars((string)($edit_row['email'] ?? '')) ?>">
          </div>
          <div class="col-12 col-lg-2">
            <label class="form-label">NIT</label>
            <input class="form-control" name="nit" value="<?= htmlspecialchars((string)($edit_row['nit'] ?? '')) ?>">
          </div>
          <div class="col-12 col-lg-8">
            <label class="form-label">Dirección</label>
            <input class="form-control" name="direccion" value="<?= htmlspecialchars((string)($edit_row['direccion'] ?? '')) ?>">
          </div>
          <div class="col-12 col-lg-2">
            <label class="form-label">Estado</label>
            <select class="form-select" name="estado">
              <option value="ACTIVO" <?= ((string)$edit_row['estado']==='ACTIVO')?'selected':'' ?>>ACTIVO</option>
              <option value="INACTIVO" <?= ((string)$edit_row['estado']==='INACTIVO')?'selected':'' ?>>INACTIVO</option>
            </select>
          </div>
          <div class="col-12 col-lg-2">
            <label class="form-label">Observaciones</label>
            <input class="form-control" name="observaciones" value="<?= htmlspecialchars((string)($edit_row['observaciones'] ?? '')) ?>">
          </div>
          <div class="col-12">
            <button class="btn btn-warning btn-pill">Guardar cambios</button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>
<!-- LISTADO -->
<div class="card card-soft shadow-sm">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0">Listado</h5>
      <form class="d-flex gap-2" method="get">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Buscar nombre, teléfono, email, NIT...">
        <select name="estado" class="form-select" data-style="clientes-1">
          <option value="" <?= ($estado_f==='')?'selected':'' ?>>Todos</option>
          <option value="ACTIVO" <?= ($estado_f==='ACTIVO')?'selected':'' ?>>Activos</option>
          <option value="INACTIVO" <?= ($estado_f==='INACTIVO')?'selected':'' ?>>Inactivos</option>
        </select>
        <button class="btn btn-outline-primary btn-pill">Filtrar</button>
        <a href="clientes.php" class="btn btn-outline-secondary btn-pill">Limpiar</a>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr>
            <th data-style="clientes-2">#</th>
            <th>Cliente</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>NIT</th>
            <th>Estado</th>
            <th>Creado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center py-4 text-muted">Sin clientes.</td></tr>
          <?php endif; ?>
          <?php $n=0; foreach ($rows as $r): $n++; ?>
            <?php $isActive = ((string)$r['estado'] === 'ACTIVO'); ?>
            <tr>
              <td><?= $n ?></td>
              <td class="fw-semibold"><?= htmlspecialchars((string)$r['nombres'].' '.(string)$r['apellidos']) ?></td>
              <td><?= htmlspecialchars((string)$r['telefono']) ?></td>
              <td><?= htmlspecialchars((string)($r['email'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string)($r['nit'] ?? '')) ?></td>
              <td>
                <?php if ($isActive): ?>
                  <span class="badge text-bg-success">ACTIVO</span>
                <?php else: ?>
                  <span class="badge text-bg-danger">INACTIVO</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string)$r['creado_en']) ?></td>
              <td class="text-end">
                <?php if (can('clientes.gestionar')): ?>
                <div class="d-inline-flex gap-2">
                  <a href="clientes.php?edit=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary btn-pill">Editar</a>
                  <form method="post" action="clientes.php" class="m-0" data-confirm="¿Seguro?">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-pill">
                      <?= $isActive ? 'Desactivar' : 'Activar' ?>
                    </button>
                  </form>
                </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="small text-muted mt-3">
      * No se borran clientes: se desactivan para mantener historial.
    </div>
  </div>
</div>
<?php
require __DIR__ . '/../layout/footer.php';
