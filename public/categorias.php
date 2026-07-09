<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

start_app_session();
require_login();

$pdo = db();

function normalize_cat(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s ?? '';
}

function count_articulos_in_categoria(PDO $pdo, int $categoria_id): int {
  $st = $pdo->prepare("SELECT COUNT(*) AS n FROM articulos WHERE categoria_id = ?");
  $st->execute([$categoria_id]);
  $row = $st->fetch();
  return (int)($row['n'] ?? 0);
}

$error = null;

// Mostrar modal/estado de edición por GET
$edit_id = (int)($_GET['edit'] ?? 0);
$edit_row = null;

if ($edit_id > 0) {
  $st = $pdo->prepare("SELECT id, nombre, activo FROM categorias WHERE id = ? LIMIT 1");
  $st->execute([$edit_id]);
  $edit_row = $st->fetch();
  if (!$edit_row) {
    flash_set('err', "Categoría no encontrada.");
    header('Location: categorias.php');
    exit;
  }
}

// ---------- POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? null)) {
    flash_set('err', 'Token inválido. Recargá la página.');
    header('Location: categorias.php');
    exit;
  }

  $action = (string)($_POST['action'] ?? '');

  // Crear
  if ($action === 'create') {
    $nombre = normalize_cat((string)($_POST['nombre'] ?? ''));
    $activo = ((string)($_POST['activo'] ?? '1') === '1') ? 1 : 0;

    if ($nombre === '') {
      $error = "El nombre es obligatorio.";
    } else {
      // Único (case-insensitive)
      $chk = $pdo->prepare("SELECT id FROM categorias WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
      $chk->execute([$nombre]);
      if ($chk->fetch()) {
        $error = "Esa categoría ya existe.";
      } else {
        $ins = $pdo->prepare("INSERT INTO categorias (nombre, activo) VALUES (?,?)");
        $ins->execute([$nombre, $activo]);
        flash_set('ok', "Categoría creada.");
        header('Location: categorias.php');
        exit;
      }
    }
  }

  // Editar
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = normalize_cat((string)($_POST['nombre'] ?? ''));
    $activo = ((string)($_POST['activo'] ?? '1') === '1') ? 1 : 0;

    if ($id <= 0) {
      flash_set('err', "ID inválido.");
      header('Location: categorias.php');
      exit;
    }

    // ✅ Bloqueo: si quieren desactivar desde edición y tiene artículos -> NO
    if ($activo === 0) {
      $n = count_articulos_in_categoria($pdo, $id);
      if ($n > 0) {
        $error = "No se puede desactivar: la categoría tiene {$n} artículo(s) asociados. Reasigná primero.";
      }
    }

    if (!$error) {
      if ($nombre === '') {
        $error = "El nombre es obligatorio.";
      } else {
        $chk = $pdo->prepare("SELECT id FROM categorias WHERE LOWER(nombre) = LOWER(?) AND id <> ? LIMIT 1");
        $chk->execute([$nombre, $id]);
        if ($chk->fetch()) {
          $error = "Ya existe otra categoría con ese nombre.";
        } else {
          $up = $pdo->prepare("UPDATE categorias SET nombre = ?, activo = ? WHERE id = ?");
          $up->execute([$nombre, $activo, $id]);
          flash_set('ok', "Categoría actualizada.");
          header('Location: categorias.php');
          exit;
        }
      }
    }
  }

  // Toggle activo
  if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      flash_set('err', "ID inválido.");
      header('Location: categorias.php');
      exit;
    }

    $st = $pdo->prepare("SELECT activo FROM categorias WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
      flash_set('err', "Categoría no encontrada.");
      header('Location: categorias.php');
      exit;
    }

    $nuevo = ((int)$row['activo'] === 1) ? 0 : 1;

    // ✅ Bloqueo: si vamos a desactivar y tiene artículos -> NO
    if ($nuevo === 0) {
      $n = count_articulos_in_categoria($pdo, $id);
      if ($n > 0) {
        flash_set('err', "No se puede desactivar: la categoría tiene {$n} artículo(s) asociados. Reasigná primero.");
        header('Location: categorias.php');
        exit;
      }
    }

    $up = $pdo->prepare("UPDATE categorias SET activo = ? WHERE id = ?");
    $up->execute([$nuevo, $id]);

    flash_set('ok', $nuevo ? "Categoría activada." : "Categoría desactivada.");
    header('Location: categorias.php');
    exit;
  }
}

// ---------- LISTADO ----------
$q = trim((string)($_GET['q'] ?? ''));

$sql = "
  SELECT
    c.id,
    c.nombre,
    c.activo,
    c.creado_en,
    COUNT(a.id) AS articulos
  FROM categorias c
  LEFT JOIN articulos a ON a.categoria_id = c.id
  WHERE 1=1
";
$params = [];

if ($q !== '') {
  $sql .= " AND c.nombre LIKE ? ";
  $params[] = "%{$q}%";
}

$sql .= "
  GROUP BY c.id, c.nombre, c.activo, c.creado_en
  ORDER BY c.nombre ASC
";

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
</style>

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
            <th style="width:70px;">#</th>
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

                  <form method="post" action="categorias.php" class="m-0" onsubmit="return confirm('¿Seguro?');">
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
require __DIR__ . '/../app/views/layout/footer.php';
