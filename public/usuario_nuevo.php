<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

start_app_session();
require_login();

if (!is_admin()) {
  http_response_code(403);
  echo "No autorizado.";
  exit;
}

$pdo = db();

$error = null;
$nombre = '';
$usuario = '';
$rol = 'OPERADOR';
$activo = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? null)) {
    $error = "Token inválido. Recargá la página.";
  } else {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $rol = (string)($_POST['rol'] ?? 'OPERADOR');
    $activo = isset($_POST['activo']) ? 1 : 0;

    $pass1 = (string)($_POST['pass1'] ?? '');
    $pass2 = (string)($_POST['pass2'] ?? '');

    if ($nombre === '' || $usuario === '') {
      $error = "Nombre y usuario son obligatorios.";
    } elseif (!in_array($rol, ['ADMIN','OPERADOR'], true)) {
      $error = "Rol inválido.";
    } elseif ($pass1 === '' || $pass2 === '') {
      $error = "Contraseña obligatoria.";
    } elseif ($pass1 !== $pass2) {
      $error = "Las contraseñas no coinciden.";
    } elseif (strlen($pass1) < 6) {
      $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
      // usuario único
      $st = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
      $st->execute([$usuario]);
      if ($st->fetch()) {
        $error = "Ese usuario ya existe.";
      } else {
        $hash = password_hash($pass1, PASSWORD_BCRYPT);
        $ins = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo) VALUES (?,?,?,?,?)");
        $ins->execute([$nombre, $usuario, $hash, $rol, $activo]);

        flash_set('ok', "Usuario creado correctamente.");
        header('Location: usuarios.php');
        exit;
      }
    }
  }
}

require __DIR__ . '/../app/views/layout/header.php';
require __DIR__ . '/../app/views/layout/sidebar.php';
?>
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
require __DIR__ . '/../app/views/layout/footer.php';
