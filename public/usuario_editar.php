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
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo "ID inválido."; exit; }

$st = $pdo->prepare("SELECT id, nombre, usuario, rol, activo FROM usuarios WHERE id = ? LIMIT 1");
$st->execute([$id]);
$row = $st->fetch();

if (!$row) { echo "Usuario no existe."; exit; }

$error = null;
$nombre = (string)$row['nombre'];
$usuario = (string)$row['usuario'];
$rol = (string)$row['rol'];
$activo = (int)$row['activo'];

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
    } else {
      // usuario único (excepto el mismo)
      $chk = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id <> ? LIMIT 1");
      $chk->execute([$usuario, $id]);
      if ($chk->fetch()) {
        $error = "Ese usuario ya existe.";
      } else {
        $pdo->beginTransaction();
        try {
          $up = $pdo->prepare("UPDATE usuarios SET nombre=?, usuario=?, rol=?, activo=? WHERE id=?");
          $up->execute([$nombre, $usuario, $rol, $activo, $id]);

          // Cambiar password solo si lo llenan
          if ($pass1 !== '' || $pass2 !== '') {
            if ($pass1 !== $pass2) throw new RuntimeException("Las contraseñas no coinciden.");
            if (strlen($pass1) < 6) throw new RuntimeException("La contraseña debe tener al menos 6 caracteres.");

            $hash = password_hash($pass1, PASSWORD_BCRYPT);
            $up2 = $pdo->prepare("UPDATE usuarios SET password_hash=? WHERE id=?");
            $up2->execute([$hash, $id]);
          }

          $pdo->commit();
          flash_set('ok', "Usuario actualizado.");
          header('Location: usuarios.php');
          exit;
        } catch (Throwable $e) {
          $pdo->rollBack();
          $error = $e->getMessage();
        }
      }
    }
  }
}

require __DIR__ . '/../app/views/layout/header.php';
require __DIR__ . '/../app/views/layout/sidebar.php';
?>
<h3 class="mb-3">Editar usuario #<?= (int)$id ?></h3>

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
          <label class="form-label">Nueva contraseña (opcional)</label>
          <input type="password" class="form-control" name="pass1" placeholder="Dejar vacío para no cambiar">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Confirmar (opcional)</label>
          <input type="password" class="form-control" name="pass2" placeholder="Dejar vacío para no cambiar">
        </div>

        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= $activo===1?'checked':'' ?>>
            <label class="form-check-label" for="activo">Activo</label>
          </div>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary">Guardar cambios</button>
          <a href="usuarios.php" class="btn btn-outline-secondary">Cancelar</a>
        </div>

        <?php if ((int)$id === (int)current_user()['id']): ?>
          <div class="col-12">
            <div class="alert alert-info mb-0">
              Estás editando tu propio usuario.
            </div>
          </div>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php
require __DIR__ . '/../app/views/layout/footer.php';
