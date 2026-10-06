<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<h3 class="mb-3">Nuevo usuario</h3>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<div class="card">
  <div class="card-body">
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">Nombre</label>
          <input class="form-control" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Usuario</label>
          <input class="form-control" name="usuario" value="<?= htmlspecialchars($usuario) ?>" required>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Rol</label>
          <select class="form-select" name="rol">
            <option value="OPERADOR" <?= $rol==='OPERADOR'?'selected':'' ?>>OPERADOR</option>
            <option value="ADMIN" <?= $rol==='ADMIN'?'selected':'' ?>>ADMIN</option>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Contraseña</label>
          <input type="password" class="form-control" name="pass1" required>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Confirmar contraseña</label>
          <input type="password" class="form-control" name="pass2" required>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= $activo===1?'checked':'' ?>>
            <label class="form-check-label" for="activo">Activo</label>
          </div>
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary">Guardar</button>
          <a href="usuarios.php" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php
require __DIR__ . '/../layout/footer.php';
