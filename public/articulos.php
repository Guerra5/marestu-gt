<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

start_app_session();
require_login();

$pdo = db();

function normalize_text(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s ?? '';
}

function next_art_code(PDO $pdo): string {
  // Genera ART-000001 basado en el mayor ID existente (robusto y simple)
  $st = $pdo->query("SELECT id FROM articulos ORDER BY id DESC LIMIT 1");
  $row = $st->fetch();
  $next = $row ? ((int)$row['id'] + 1) : 1;
  return 'ART-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

/** TODAS las categorías (para filtros y edición) */
function get_categories_all(PDO $pdo): array {
  $st = $pdo->query("SELECT id, nombre, activo FROM categorias ORDER BY nombre ASC");
  return $st->fetchAll();
}

/** SOLO categorías ACTIVAS (para crear / operación) */
function get_categories_active(PDO $pdo): array {
  $st = $pdo->query("SELECT id, nombre, activo FROM categorias WHERE activo=1 ORDER BY nombre ASC");
  return $st->fetchAll();
}

$error = null;

// --------- CARGAR CATEGORÍAS ----------
$cats_all = get_categories_all($pdo);
$cats_active = get_categories_active($pdo); // para CREAR

// --------- EDIT MODE ----------
$edit_id = (int)($_GET['edit'] ?? 0);
$edit_row = null;

if ($edit_id > 0) {
  $st = $pdo->prepare("SELECT a.*, c.nombre AS categoria_nombre
                       FROM articulos a
                       INNER JOIN categorias c ON c.id = a.categoria_id
                       WHERE a.id = ? LIMIT 1");
  $st->execute([$edit_id]);
  $edit_row = $st->fetch();

  if (!$edit_row) {
    flash_set('err', "Artículo no encontrado.");
    header('Location: articulos.php');
    exit;
  }
}

// --------- POST ACTIONS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? null)) {
    flash_set('err', 'Token inválido. Recargá la página.');
    header('Location: articulos.php');
    exit;
  }

  $action = (string)($_POST['action'] ?? '');

  // CREATE
  if ($action === 'create') {
    $nombre = normalize_text((string)($_POST['nombre'] ?? ''));
    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $unidad = normalize_text((string)($_POST['unidad'] ?? 'pza'));
    $ubicacion = normalize_text((string)($_POST['ubicacion'] ?? ''));
    $observaciones = trim((string)($_POST['observaciones'] ?? ''));

    $precio_unitario = (float)($_POST['precio_unitario'] ?? 0);
    if ($precio_unitario < 0) $precio_unitario = 0;

    $cantidad_total = (int)($_POST['cantidad_total'] ?? 0);
    $cantidad_activa = (int)($_POST['cantidad_activa'] ?? 0);

    // 🔒 Validación backend: categoría debe estar ACTIVA
    $stCat = $pdo->prepare("SELECT activo FROM categorias WHERE id=? LIMIT 1");
    $stCat->execute([$categoria_id]);
    $catRow = $stCat->fetch();

    if ($nombre === '') {
      $error = "El nombre es obligatorio.";
    } elseif ($categoria_id <= 0) {
      $error = "Seleccioná una categoría.";
    } elseif (!$catRow || (int)$catRow['activo'] !== 1) {
      $error = "No se puede usar una categoría desactivada.";
    } elseif ($unidad === '') {
      $error = "La unidad es obligatoria.";
    } elseif ($cantidad_total < 0 || $cantidad_activa < 0) {
      $error = "Las cantidades no pueden ser negativas.";
    } elseif ($cantidad_activa > $cantidad_total) {
      $error = "La cantidad activa no puede ser mayor que la total.";
    } elseif ($precio_unitario < 0) {
      $error = "El precio no puede ser negativo.";
    } else {
      $codigo = next_art_code($pdo);

      $pdo->beginTransaction();
      try {
        $ins = $pdo->prepare("INSERT INTO articulos
          (codigo, nombre, categoria_id, unidad, precio_unitario, cantidad_total, cantidad_activa, estado, ubicacion, observaciones)
          VALUES (?,?,?,?,?, ?,?, 'ACTIVO', ?, ?)");
        $ins->execute([
          $codigo, $nombre, $categoria_id, $unidad,
          $precio_unitario,
          $cantidad_total, $cantidad_activa,
          ($ubicacion !== '' ? $ubicacion : null),
          ($observaciones !== '' ? $observaciones : null),
        ]);

        $articulo_id = (int)$pdo->lastInsertId();

        // Kardex: si entra stock activo inicial, registrar ENTRADA
        if ($cantidad_activa > 0) {
          $nota = "Stock inicial. Total={$cantidad_total}, Activo={$cantidad_activa}.";
          $mov = $pdo->prepare("INSERT INTO movimientos_inventario
            (tipo, articulo_id, referencia_tipo, referencia_id, cantidad, nota, creado_por)
            VALUES ('ENTRADA', ?, 'MANUAL', NULL, ?, ?, ?)");
          $mov->execute([$articulo_id, $cantidad_activa, $nota, (int)current_user()['id']]);
        }

        $pdo->commit();
        flash_set('ok', "Artículo creado: {$codigo}");
        header('Location: articulos.php');
        exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
      }
    }
  }

  // UPDATE (datos generales)
  if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = normalize_text((string)($_POST['nombre'] ?? ''));
    $categoria_id = (int)($_POST['categoria_id'] ?? 0);
    $unidad = normalize_text((string)($_POST['unidad'] ?? 'pza'));
    $estado = (string)($_POST['estado'] ?? 'ACTIVO');
    $ubicacion = normalize_text((string)($_POST['ubicacion'] ?? ''));
    $observaciones = trim((string)($_POST['observaciones'] ?? ''));

    $precio_unitario = (float)($_POST['precio_unitario'] ?? 0);
    if ($precio_unitario < 0) $precio_unitario = 0;

    if ($id <= 0) {
      flash_set('err', "ID inválido.");
      header('Location: articulos.php');
      exit;
    }

    // Traer categoría actual del artículo (para permitir mantener una categoría inactiva ya asignada)
    $stCur = $pdo->prepare("SELECT categoria_id FROM articulos WHERE id=? LIMIT 1");
    $stCur->execute([$id]);
    $cur = $stCur->fetch();

    if (!$cur) {
      flash_set('err', "Artículo no encontrado.");
      header('Location: articulos.php');
      exit;
    }

    $categoria_actual = (int)$cur['categoria_id'];

    // Validar categoría seleccionada
    $stCat = $pdo->prepare("SELECT activo FROM categorias WHERE id=? LIMIT 1");
    $stCat->execute([$categoria_id]);
    $catRow = $stCat->fetch();
    $catActiva = $catRow && (int)$catRow['activo'] === 1;

    if ($nombre === '') {
      $error = "El nombre es obligatorio.";
    } elseif ($categoria_id <= 0) {
      $error = "Seleccioná una categoría.";
    } elseif (!$catRow) {
      $error = "La categoría no existe.";
    } elseif (!$catActiva && $categoria_id !== $categoria_actual) {
      // 🔒 No permitir cambiar a una categoría inactiva
      $error = "No podés asignar una categoría desactivada (solo se permite mantener la actual por historial).";
    } elseif ($unidad === '') {
      $error = "La unidad es obligatoria.";
    } elseif (!in_array($estado, ['ACTIVO','INACTIVO'], true)) {
      $error = "Estado inválido.";
    } else {
      $up = $pdo->prepare("UPDATE articulos
        SET nombre=?, categoria_id=?, unidad=?, precio_unitario=?, estado=?, ubicacion=?, observaciones=?
        WHERE id=?");
      $up->execute([
        $nombre,
        $categoria_id,
        $unidad,
        $precio_unitario,
        $estado,
        ($ubicacion !== '' ? $ubicacion : null),
        ($observaciones !== '' ? $observaciones : null),
        $id
      ]);

      flash_set('ok', "Artículo actualizado.");
      header('Location: articulos.php?edit=' . $id);
      exit;
    }
  }

  // STOCK UPDATE (ajuste de cantidades + kardex)
  if ($action === 'stock') {
    $id = (int)($_POST['id'] ?? 0);
    $nuevo_total = (int)($_POST['cantidad_total'] ?? 0);
    $nuevo_activo = (int)($_POST['cantidad_activa'] ?? 0);
    $nota_user = trim((string)($_POST['nota'] ?? ''));

    if ($id <= 0) {
      flash_set('err', "ID inválido.");
      header('Location: articulos.php');
      exit;
    }
    if ($nuevo_total < 0 || $nuevo_activo < 0) {
      $error = "Las cantidades no pueden ser negativas.";
    } elseif ($nuevo_activo > $nuevo_total) {
      $error = "La cantidad activa no puede ser mayor que la total.";
    } else {
      $st = $pdo->prepare("SELECT id, cantidad_total, cantidad_activa FROM articulos WHERE id=? LIMIT 1");
      $st->execute([$id]);
      $row = $st->fetch();

      if (!$row) {
        flash_set('err', "Artículo no encontrado.");
        header('Location: articulos.php');
        exit;
      }

      $old_total = (int)$row['cantidad_total'];
      $old_activo = (int)$row['cantidad_activa'];

      $diff_activo = $nuevo_activo - $old_activo;

      $pdo->beginTransaction();
      try {
        $up = $pdo->prepare("UPDATE articulos SET cantidad_total=?, cantidad_activa=? WHERE id=?");
        $up->execute([$nuevo_total, $nuevo_activo, $id]);

        // Kardex: registramos solo si cambió el ACTIVO (lo alquilable)
        if ($diff_activo !== 0) {
          $tipo = 'AJUSTE';
          $nota = "Ajuste stock. Total: {$old_total}→{$nuevo_total}, Activo: {$old_activo}→{$nuevo_activo}.";
          if ($nota_user !== '') $nota .= " Nota: {$nota_user}";
          $mov = $pdo->prepare("INSERT INTO movimientos_inventario
            (tipo, articulo_id, referencia_tipo, referencia_id, cantidad, nota, creado_por)
            VALUES (?, ?, 'MANUAL', NULL, ?, ?, ?)");
          $mov->execute([$tipo, $id, $diff_activo, $nota, (int)current_user()['id']]);
        }

        $pdo->commit();
        flash_set('ok', "Stock actualizado.");
        header('Location: articulos.php?edit=' . $id);
        exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
      }
    }
  }

  // TOGGLE activo/inactivo (rápido)
  if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
      flash_set('err', "ID inválido.");
      header('Location: articulos.php');
      exit;
    }

    $st = $pdo->prepare("SELECT estado FROM articulos WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
      flash_set('err', "Artículo no encontrado.");
      header('Location: articulos.php');
      exit;
    }

    $nuevo = ((string)$row['estado'] === 'ACTIVO') ? 'INACTIVO' : 'ACTIVO';
    $up = $pdo->prepare("UPDATE articulos SET estado=? WHERE id=?");
    $up->execute([$nuevo, $id]);

    flash_set('ok', $nuevo === 'ACTIVO' ? "Artículo activado." : "Artículo desactivado.");
    header('Location: articulos.php');
    exit;
  }
}

// --------- LISTADO / FILTROS ----------
$q = trim((string)($_GET['q'] ?? ''));
$cat = (int)($_GET['cat'] ?? 0);
$estado_f = (string)($_GET['estado'] ?? '');

$sql = "SELECT a.id, a.codigo, a.nombre, a.unidad, a.precio_unitario, a.cantidad_total, a.cantidad_activa, a.estado,
               c.nombre AS categoria
        FROM articulos a
        INNER JOIN categorias c ON c.id = a.categoria_id
        WHERE 1=1";
$params = [];

if ($q !== '') {
  $sql .= " AND (a.nombre LIKE ? OR a.codigo LIKE ?) ";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
}
if ($cat > 0) {
  $sql .= " AND a.categoria_id = ? ";
  $params[] = $cat;
}
if ($estado_f === 'ACTIVO' || $estado_f === 'INACTIVO') {
  $sql .= " AND a.estado = ? ";
  $params[] = $estado_f;
}

$sql .= " ORDER BY a.id DESC";
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
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>

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
              $stTmp = $pdo->prepare("SELECT activo FROM categorias WHERE id=? LIMIT 1");
              $stTmp->execute([(int)$edit_row['categoria_id']]);
              $tmpCat = $stTmp->fetch();
              if ($tmpCat && (int)$tmpCat['activo'] === 0) echo ' (Inactiva)';
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

        <select name="cat" class="form-select" style="min-width: 190px;">
          <option value="0">Todas las categorías</option>
          <?php foreach ($cats_all as $c): ?>
            <?php $label = (string)$c['nombre'] . (((int)$c['activo']===0) ? ' (Inactiva)' : ''); ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($cat===(int)$c['id'])?'selected':'' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select name="estado" class="form-select" style="min-width: 140px;">
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
            <th style="width:60px;">#</th>
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

                  <form method="post" action="articulos.php" class="m-0" onsubmit="return confirm('¿Seguro?');">
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
require __DIR__ . '/../app/views/layout/footer.php';
