<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

start_app_session();
require_login();

$pdo = db();

function norm(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s ?? '';
}

function norm_email(?string $s): ?string {
  $s = trim((string)$s);
  if ($s === '') return null;
  $s = strtolower($s);
  return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : null;
}

$error = null;

// -------- EDIT MODE ----------
$edit_id = (int)($_GET['edit'] ?? 0);
$edit_row = null;

if ($edit_id > 0) {
  $st = $pdo->prepare("SELECT * FROM clientes WHERE id = ? LIMIT 1");
  $st->execute([$edit_id]);
  $edit_row = $st->fetch();

  if (!$edit_row) {
    flash_set('err', "Cliente no encontrado.");
    header('Location: clientes.php');
    exit;
  }
}

// -------- POST ACTIONS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? null)) {
    flash_set('err', 'Token inválido. Recargá la página.');
    header('Location: clientes.php');
    exit;
  }

  $action = (string)($_POST['action'] ?? '');

  // CREATE
  if ($action === 'create') {
    $nombres = norm((string)($_POST['nombres'] ?? ''));
    $apellidos = norm((string)($_POST['apellidos'] ?? ''));
    $telefono = norm((string)($_POST['telefono'] ?? ''));
    $email = norm_email($_POST['email'] ?? null);
    $direccion = norm((string)($_POST['direccion'] ?? ''));
    $nit = norm((string)($_POST['nit'] ?? ''));
    $observaciones = trim((string)($_POST['observaciones'] ?? ''));
    $estado = ((string)($_POST['estado'] ?? 'ACTIVO') === 'INACTIVO') ? 'INACTIVO' : 'ACTIVO';

    if ($nombres === '' || $apellidos === '') {
      $error = "Nombres y apellidos son obligatorios.";
    } elseif ($telefono === '') {
      $error = "El teléfono es obligatorio.";
    } elseif (isset($_POST['email']) && trim((string)$_POST['email']) !== '' && $email === null) {
      $error = "Email inválido.";
    } else {
      $ins = $pdo->prepare("INSERT INTO clientes
        (nombres, apellidos, telefono, email, direccion, nit, observaciones, estado)
        VALUES (?,?,?,?,?,?,?,?)");
      $ins->execute([
        $nombres,
        $apellidos,
        $telefono,
        $email,
        ($direccion !== '' ? $direccion : null),
        ($nit !== '' ? $nit : null),
        ($observaciones !== '' ? $observaciones : null),
        $estado
      ]);

      flash_set('ok', "Cliente creado.");
      header('Location: clientes.php');
      exit;
    }
  }

  // UPDATE
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $nombres = norm((string)($_POST['nombres'] ?? ''));
    $apellidos = norm((string)($_POST['apellidos'] ?? ''));
    $telefono = norm((string)($_POST['telefono'] ?? ''));
    $email = norm_email($_POST['email'] ?? null);
    $direccion = norm((string)($_POST['direccion'] ?? ''));
    $nit = norm((string)($_POST['nit'] ?? ''));
    $observaciones = trim((string)($_POST['observaciones'] ?? ''));
    $estado = ((string)($_POST['estado'] ?? 'ACTIVO') === 'INACTIVO') ? 'INACTIVO' : 'ACTIVO';

    if ($id <= 0) {
      flash_set('err', "ID inválido.");
      header('Location: clientes.php');
      exit;
    }

    if ($nombres === '' || $apellidos === '') {
      $error = "Nombres y apellidos son obligatorios.";
    } elseif ($telefono === '') {
      $error = "El teléfono es obligatorio.";
    } elseif (isset($_POST['email']) && trim((string)$_POST['email']) !== '' && $email === null) {
      $error = "Email inválido.";
    } else {
      $up = $pdo->prepare("UPDATE clientes SET
        nombres=?, apellidos=?, telefono=?, email=?, direccion=?, nit=?, observaciones=?, estado=?
        WHERE id=?");
      $up->execute([
        $nombres,
        $apellidos,
        $telefono,
        $email,
        ($direccion !== '' ? $direccion : null),
        ($nit !== '' ? $nit : null),
        ($observaciones !== '' ? $observaciones : null),
        $estado,
        $id
      ]);

      flash_set('ok', "Cliente actualizado.");
      header('Location: clientes.php');
      exit;
    }
  }

  // TOGGLE ACTIVO/INACTIVO
  if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      flash_set('err', "ID inválido.");
      header('Location: clientes.php');
      exit;
    }

    $st = $pdo->prepare("SELECT estado FROM clientes WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();

    if (!$row) {
      flash_set('err', "Cliente no encontrado.");
      header('Location: clientes.php');
      exit;
    }

    $nuevo = ((string)$row['estado'] === 'ACTIVO') ? 'INACTIVO' : 'ACTIVO';
    $up = $pdo->prepare("UPDATE clientes SET estado=? WHERE id=?");
    $up->execute([$nuevo, $id]);

    flash_set('ok', $nuevo === 'ACTIVO' ? "Cliente activado." : "Cliente desactivado.");
    header('Location: clientes.php');
    exit;
  }
}

// -------- LISTADO / BUSCADOR ----------
$q = trim((string)($_GET['q'] ?? ''));
$estado_f = (string)($_GET['estado'] ?? '');

$sql = "SELECT id, nombres, apellidos, telefono, email, nit, estado, creado_en
        FROM clientes
        WHERE 1=1";
$params = [];

if ($q !== '') {
  $sql .= " AND (
    nombres LIKE ? OR apellidos LIKE ? OR telefono LIKE ? OR email LIKE ? OR nit LIKE ?
  )";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
}

if ($estado_f === 'ACTIVO' || $estado_f === 'INACTIVO') {
  $sql .= " AND estado = ? ";
  $params[] = $estado_f;
}

$sql .= " ORDER BY id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

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
</style>

<div class="page-topbar mb-3">
  <div>
    <div class="text-muted small">Clientes</div>
    <h3 class="mb-1">Gestión de clientes</h3>
    <div class="page-subtitle">Crear, editar y activar/desactivar clientes.</div>
  </div>
  <div>
    <a href="clientes.php" class="btn btn-outline-secondary btn-pill">Refrescar</a>
  </div>
</div>

<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

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

        <select name="estado" class="form-select" style="min-width: 150px;">
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
            <th style="width:60px;">#</th>
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
                <div class="d-inline-flex gap-2">
                  <a href="clientes.php?edit=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary btn-pill">Editar</a>

                  <form method="post" action="clientes.php" class="m-0" onsubmit="return confirm('¿Seguro?');">
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
      * No se borran clientes: se desactivan para mantener historial.
    </div>
  </div>
</div>

<?php
require __DIR__ . '/../app/views/layout/footer.php';
