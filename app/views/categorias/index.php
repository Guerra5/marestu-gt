<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<link rel="stylesheet" href="assets/css/pages/categorias.css">
<div class="page-topbar mb-3">
  <div>
    <div class="text-muted small">Catálogo</div>
    <h3 class="mb-1">Categorías</h3>
    <div class="page-subtitle">Crear, editar y activar/desactivar categorías.</div>
  </div>
  <div>
    <a href="categorias.php" class="btn btn-outline-secondary btn-pill">Refrescar</a>
  </div>
</div>
<?php if ($ok): ?>
  <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<!-- CREAR -->
<div class="card card-soft shadow-sm mb-4">
  <div class="card-body p-4">
    <h5 class="mb-3">Crear categoría</h5>
    <form method="post" class="row g-3">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="col-12 col-lg-8">
        <label class="form-label">Nombre</label>
        <input class="form-control" name="nombre" required placeholder="Ej: Sillas, Mesas, Mantelería...">
      </div>
      <div class="col-12 col-lg-2">
        <label class="form-label">Activo</label>
        <select class="form-select" name="activo">
          <option value="1" selected>Sí</option>
          <option value="0">No</option>
        </select>
      </div>
      <div class="col-12 col-lg-2 d-flex align-items-end">
        <button class="btn btn-primary btn-pill w-100">Crear</button>
      </div>
    </form>
  </div>
</div>
<!-- EDITAR (si viene ?edit=ID) -->
<?php if ($edit_row): ?>
  <div class="card card-soft shadow-sm mb-4 border border-warning">
    <div class="card-body p-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0">Editar categoría #<?= (int)$edit_row['id'] ?></h5>
        <a href="categorias.php" class="btn btn-outline-secondary btn-pill btn-sm">Cancelar edición</a>
      </div>
      <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int)$edit_row['id'] ?>">
        <div class="col-12 col-lg-8">
          <label class="form-label">Nombre</label>
          <input class="form-control" name="nombre" required value="<?= htmlspecialchars((string)$edit_row['nombre']) ?>">
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label">Activo</label>
          <select class="form-select" name="activo">
            <option value="1" <?= ((int)$edit_row['activo']===1)?'selected':'' ?>>Sí</option>
            <option value="0" <?= ((int)$edit_row['activo']===0)?'selected':'' ?>>No</option>
          </select>
          <div class="small text-muted mt-1">
            * No se permite desactivar si hay artículos asociados.
          </div>
        </div>
        <div class="col-12 col-lg-2 d-flex align-items-end">
          <button class="btn btn-warning btn-pill w-100">Guardar</button>
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
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Buscar...">
        <button class="btn btn-outline-primary btn-pill">Buscar</button>
        <a href="categorias.php" class="btn btn-outline-secondary btn-pill">Limpiar</a>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr>
            <th data-style="categorias-1">#</th>
            <th>Nombre</th>
            <th>Artículos</th>
            <th>Activo</th>
            <th>Creado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center py-4 text-muted">Sin categorías.</td></tr>
          <?php endif; ?>
          <?php $n=0; foreach ($rows as $r): $n++; ?>
            <?php $isActive = ((int)$r['activo']===1); ?>
            <tr>
              <td><?= $n ?></td>
              <td><?= htmlspecialchars((string)$r['nombre']) ?></td>
              <td>
                <span class="badge text-bg-secondary"><?= (int)$r['articulos'] ?></span>
              </td>
              <td>
                <?php if ($isActive): ?>
                  <span class="badge text-bg-success">Sí</span>
                <?php else: ?>
                  <span class="badge text-bg-danger">No</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string)$r['creado_en']) ?></td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <a href="categorias.php?edit=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary btn-pill">Editar</a>
                  <form method="post" action="categorias.php" class="m-0" data-confirm="¿Seguro?">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-pill"
                      <?= (!$isActive || (int)$r['articulos'] === 0) ? '' : 'title="Tiene artículos asociados"' ?>>
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
      * No se permite desactivar una categoría si tiene artículos asociados (para evitar inconsistencias).
    </div>
  </div>
</div>
<?php
require __DIR__ . '/../layout/footer.php';
