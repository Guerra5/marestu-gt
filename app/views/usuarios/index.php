<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<link rel="stylesheet" href="assets/css/pages/usuarios.css">
<div class="page-topbar mb-3">
  <div>
    <div class="text-muted small">Administración</div>
    <h3 class="mb-1">Usuarios</h3>
    <div class="page-subtitle">Crear, editar, activar/desactivar y resetear contraseñas.</div>
  </div>
  <div>
    <a href="usuarios.php" class="btn btn-outline-secondary btn-pill">Refrescar</a>
  </div>
</div>
<?php if ($ok): ?>
  <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>
<?php if ($temp_password_shown): ?>
  <div class="alert alert-warning">
    <b>Contraseña temporal:</b>
    <code data-style="usuarios-1"><?= htmlspecialchars($temp_password_shown) ?></code>
    <div class="small text-muted mt-1">Se muestra una sola vez. Copiala y compartila de forma segura.</div>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<!-- CARD: CREAR USUARIO -->
<div class="card card-soft shadow-sm mb-4">
  <div class="card-body p-4">
    <h5 class="mb-3">Crear usuario</h5>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <label class="form-label">Nombre</label>
          <input class="form-control" name="nombre" required>
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Usuario (username)</label>
          <input class="form-control" name="usuario" required>
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Rol</label>
          <select class="form-select" name="rol">
            <option value="OPERADOR" selected>OPERADOR</option>
            <option value="ADMIN">ADMIN</option>
          </select>
        </div>
        <div class="col-12 col-lg-2">
          <label class="form-label">Activo</label>
          <select class="form-select" name="activo">
            <option value="1" selected>Sí</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Contraseña</label>
          <input type="password" class="form-control" name="pass1" required>
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Confirmar</label>
          <input type="password" class="form-control" name="pass2" required>
        </div>
        <div class="col-12">
          <div class="small text-muted">* La contraseña se guarda en hash (no se guarda en texto plano).</div>
        </div>
        <div class="col-12">
          <button class="btn btn-primary btn-pill">Crear usuario</button>
        </div>
      </div>
    </form>
  </div>
</div>
<!-- CARD: LISTADO -->
<div class="card card-soft shadow-sm">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0">Listado</h5>
      <form class="d-flex gap-2" method="get">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Buscar...">
        <button class="btn btn-outline-primary btn-pill">Buscar</button>
        <a href="usuarios.php" class="btn btn-outline-secondary btn-pill">Limpiar</a>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr>
            <th data-style="usuarios-2">#</th>
            <th>Nombre</th>
            <th>Usuario</th>
            <th>Rol</th>
            <th>Activo</th>
            <th>Creado</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">Sin usuarios.</td></tr>
          <?php endif; ?>
          <?php $n = 0; foreach ($rows as $r): $n++; ?>
            <?php
              $isActive = ((int)$r['activo'] === 1);
              $isMe = ((int)$r['id'] === (int)current_user()['id']);
              $role = (string)$r['rol'];
            ?>
            <tr>
              <td><?= $n ?></td>
              <td><?= htmlspecialchars((string)$r['nombre']) ?></td>
              <td><?= htmlspecialchars((string)$r['usuario']) ?></td>
              <td>
                <?php if ($role === 'ADMIN'): ?>
                  <span class="badge text-bg-primary badge-role">ADMIN</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary badge-role">OPERADOR</span>
                <?php endif; ?>
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
                  <a class="btn btn-sm btn-outline-primary btn-pill"
                     href="usuario_editar.php?id=<?= (int)$r['id'] ?>">Editar</a>
                  <form method="post" action="usuarios.php" class="m-0"
                        data-confirm="¿Resetear contraseña? Se mostrará una contraseña temporal una sola vez.">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-warning btn-pill">Reset</button>
                  </form>
                  <?php if ($isMe): ?>
                    <button class="btn btn-sm btn-outline-secondary btn-pill" disabled>Tu usuario</button>
                  <?php else: ?>
                    <form method="post" action="usuarios.php" class="m-0"
                          data-confirm="¿Seguro?">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-pill">
                        <?= $isActive ? 'Desactivar' : 'Activar' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="small text-muted mt-3">
      * No se permite desactivar tu propio usuario para evitar quedarte sin acceso.<br>
      * “Reset” genera una contraseña temporal y la muestra una sola vez en pantalla.
    </div>
  </div>
</div>
<?php
require __DIR__ . '/../layout/footer.php';
