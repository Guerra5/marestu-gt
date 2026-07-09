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

function gen_temp_password(int $len = 10): string {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
  $out = '';
  for ($i=0; $i<$len; $i++) {
    $out .= $chars[random_int(0, strlen($chars)-1)];
  }
  return $out;
}

$error = null;
$temp_password_shown = flash_get('temp_pass'); // se muestra una sola vez

// ---------- ACCIONES POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? null)) {
    flash_set('err', 'Token inválido. Recargá la página.');
    header('Location: usuarios.php');
    exit;
  }

  $action = (string)($_POST['action'] ?? '');

  // Crear usuario
  if ($action === 'create') {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $rol = (string)($_POST['rol'] ?? 'OPERADOR');
    $activo = ((string)($_POST['activo'] ?? '1') === '1') ? 1 : 0;

    $pass1 = (string)($_POST['pass1'] ?? '');
    $pass2 = (string)($_POST['pass2'] ?? '');

    if ($nombre === '' || $usuario === '') {
      $error = "Nombre y usuario son obligatorios.";
    } elseif (!in_array($rol, ['ADMIN','OPERADOR'], true)) {
      $error = "Rol inválido.";
    } elseif ($pass1 === '' || $pass2 === '') {
      $error = "La contraseña es obligatoria.";
    } elseif ($pass1 !== $pass2) {
      $error = "Las contraseñas no coinciden.";
    } elseif (strlen($pass1) < 6) {
      $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
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

  // Activar / desactivar
  if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
      flash_set('err', "ID inválido.");
      header('Location: usuarios.php');
      exit;
    }

    if ($id === (int)current_user()['id']) {
      flash_set('err', "No se permite desactivar tu propio usuario.");
      header('Location: usuarios.php');
      exit;
    }

    $st = $pdo->prepare("SELECT activo FROM usuarios WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();

    if (!$row) {
      flash_set('err', "Usuario no encontrado.");
      header('Location: usuarios.php');
      exit;
    }

    $nuevo = ((int)$row['activo'] === 1) ? 0 : 1;
    $up = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
    $up->execute([$nuevo, $id]);

    flash_set('ok', $nuevo ? "Usuario activado." : "Usuario desactivado.");
    header('Location: usuarios.php');
    exit;
  }

  // Reset password (contraseña temporal)
  if ($action === 'reset') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
      flash_set('err', "ID inválido.");
      header('Location: usuarios.php');
      exit;
    }

    $st = $pdo->prepare("SELECT id, usuario FROM usuarios WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();

    if (!$row) {
      flash_set('err', "Usuario no encontrado.");
      header('Location: usuarios.php');
      exit;
    }

    $temp = gen_temp_password(10);
    $hash = password_hash($temp, PASSWORD_BCRYPT);

    $up = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
    $up->execute([$hash, $id]);

    flash_set('ok', "Contraseña reseteada para @".(string)$row['usuario'].".");
    // mostrar una sola vez:
    flash_set('temp', '1');
    $_SESSION['flash']['temp_pass'] = $temp; // se consume al cargar
    header('Location: usuarios.php');
    exit;
  }
}

// ---------- LISTADO ----------
$q = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT id, nombre, usuario, rol, activo, creado_en
        FROM usuarios
        WHERE 1=1";
$params = [];

if ($q !== '') {
  $sql .= " AND (nombre LIKE ? OR usuario LIKE ? OR rol LIKE ?) ";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
}

$sql .= " ORDER BY id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$ok  = flash_get('ok');
$err = flash_get('err');

require __DIR__ . '/../app/views/layout/header.php';
require __DIR__ . '/../app/views/layout/sidebar.php';
?>

<style>
  .page-topbar { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
  .page-subtitle { color:#6c757d; margin-top:2px; }
  .card-soft { border-radius:14px; }
  .btn-pill { border-radius:10px; }
  .badge-role { font-weight:600; }
  .table thead th { white-space:nowrap; }
</style>

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
    <code style="font-size: 1.05rem;"><?= htmlspecialchars($temp_password_shown) ?></code>
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
            <th style="width:60px;">#</th>
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
                        onsubmit="return confirm('¿Resetear contraseña? Se mostrará una contraseña temporal una sola vez.');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-warning btn-pill">Reset</button>
                  </form>

                  <?php if ($isMe): ?>
                    <button class="btn btn-sm btn-outline-secondary btn-pill" disabled>Tu usuario</button>
                  <?php else: ?>
                    <form method="post" action="usuarios.php" class="m-0"
                          onsubmit="return confirm('¿Seguro?');">
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
require __DIR__ . '/../app/views/layout/footer.php';
