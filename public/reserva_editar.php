<?php
declare(strict_types=1);
// PRUEBA
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

start_app_session();
require_login();
$pdo = db();

function get_reserva(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare("SELECT r.*, CONCAT(c.nombres,' ',c.apellidos) AS cliente, c.telefono
                       FROM reservas r
                       INNER JOIN clientes c ON c.id = r.cliente_id
                       WHERE r.id=? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch();
  return $row ?: null;
}

function get_articulos_activos(PDO $pdo): array {
  $st = $pdo->query("SELECT a.id, a.codigo, a.nombre, a.cantidad_activa, a.precio_unitario, c.nombre AS categoria
                     FROM articulos a
                     INNER JOIN categorias c ON c.id=a.categoria_id
                     WHERE a.estado='ACTIVO'
                     ORDER BY c.nombre ASC, a.nombre ASC");
  return $st->fetchAll();
}

function reserva_items(PDO $pdo, int $reserva_id): array {
  $st = $pdo->prepare("SELECT d.id, d.articulo_id, d.cantidad,
                              a.codigo, a.nombre, a.precio_unitario,
                              c.nombre AS categoria
                       FROM reserva_detalle d
                       INNER JOIN articulos a ON a.id=d.articulo_id
                       INNER JOIN categorias c ON c.id=a.categoria_id
                       WHERE d.reserva_id=?
                       ORDER BY d.id DESC");
  $st->execute([$reserva_id]);
  return $st->fetchAll();
}

function reserva_extras(PDO $pdo, int $reserva_id): array {
  $st = $pdo->prepare("SELECT id, descripcion, proveedor, cantidad, precio_unitario,
                              (cantidad * precio_unitario) AS subtotal
                       FROM reserva_extras
                       WHERE reserva_id=?
                       ORDER BY id DESC");
  $st->execute([$reserva_id]);
  return $st->fetchAll();
}

/**
 * Disponibilidad por fechas:
 * - suma cantidades de reservas que se traslapan (CONFIRMADA/ENTREGADA)
 * - excluye la reserva actual para permitir editar sin bloquearse a sí misma
 */
function reserved_qty(PDO $pdo, int $articulo_id, string $salida, string $retorno, int $exclude_reserva_id): int {
  $sql = "
    SELECT COALESCE(SUM(d.cantidad),0) AS total
    FROM reservas r
    INNER JOIN reserva_detalle d ON d.reserva_id = r.id
    WHERE d.articulo_id = ?
      AND r.estado IN ('CONFIRMADA','ENTREGADA')
      AND r.id <> ?
      AND r.fecha_salida <= ?
      AND r.fecha_retorno >= ?
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$articulo_id, $exclude_reserva_id, $retorno, $salida]);
  $row = $st->fetch();
  return (int)($row['total'] ?? 0);
}

function articulo_activo_stock(PDO $pdo, int $articulo_id): int {
  $st = $pdo->prepare("SELECT cantidad_activa FROM articulos WHERE id=? LIMIT 1");
  $st->execute([$articulo_id]);
  $row = $st->fetch();
  return $row ? (int)$row['cantidad_activa'] : 0;
}

function add_mov(PDO $pdo, string $tipo, int $articulo_id, int $reserva_id, int $cantidad, string $nota, int $user_id): void {
  $st = $pdo->prepare("INSERT INTO movimientos_inventario
    (tipo, articulo_id, referencia_tipo, referencia_id, cantidad, nota, creado_por)
    VALUES (?, ?, 'RESERVA', ?, ?, ?, ?)");
  $st->execute([$tipo, $articulo_id, $reserva_id, $cantidad, $nota, $user_id]);
}

function normalize_text(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s ?? '';
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo "ID inválido."; exit; }

$res = get_reserva($pdo, $id);
if (!$res) { echo "Reserva no encontrada."; exit; }

$items = reserva_items($pdo, $id);
$extras = reserva_extras($pdo, $id);
$arts = get_articulos_activos($pdo);

$error = null;

// ---------------- POST ACTIONS ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? null)) {
    flash_set('err', "Token inválido. Recargá.");
    header("Location: reserva_editar.php?id={$id}");
    exit;
  }

  $action = (string)($_POST['action'] ?? '');

  // --- Agregar/Actualizar artículo (solo BORRADOR)
  if ($action === 'add_item') {
    if ($res['estado'] !== 'BORRADOR') {
      flash_set('err', "Solo podés modificar artículos en estado BORRADOR.");
      header("Location: reserva_editar.php?id={$id}");
      exit;
    }

    $articulo_id = (int)($_POST['articulo_id'] ?? 0);
    $cantidad = (int)($_POST['cantidad'] ?? 0);

    if ($articulo_id <= 0) {
      $error = "Seleccioná un artículo.";
    } elseif ($cantidad <= 0) {
      $error = "Cantidad inválida.";
    } else {
      $salida = (string)$res['fecha_salida'];
      $retorno = (string)$res['fecha_retorno'];

      $stock = articulo_activo_stock($pdo, $articulo_id);
      $reservado = reserved_qty($pdo, $articulo_id, $salida, $retorno, $id);
      $disponible = $stock - $reservado;

      // si ya existe en el detalle, sumamos lo que ya tiene esta reserva
      $stx = $pdo->prepare("SELECT id, cantidad FROM reserva_detalle WHERE reserva_id=? AND articulo_id=? LIMIT 1");
      $stx->execute([$id, $articulo_id]);
      $exists = $stx->fetch();
      $ya_en_reserva = $exists ? (int)$exists['cantidad'] : 0;

      // disponibilidad total que puede tener esta reserva (incluyendo lo que ya tenía asignado)
      $max_para_esta_reserva = $disponible + $ya_en_reserva;

      if ($cantidad > $max_para_esta_reserva) {
        $error = "No hay disponibilidad suficiente para esas fechas. Disponible: {$max_para_esta_reserva}.";
      } else {
        if ($exists) {
          $upd = $pdo->prepare("UPDATE reserva_detalle SET cantidad=? WHERE id=?");
          $upd->execute([$cantidad, (int)$exists['id']]);
        } else {
          $ins = $pdo->prepare("INSERT INTO reserva_detalle (reserva_id, articulo_id, cantidad) VALUES (?,?,?)");
          $ins->execute([$id, $articulo_id, $cantidad]);
        }

        flash_set('ok', "Artículo agregado/actualizado en la reserva.");
        header("Location: reserva_editar.php?id={$id}");
        exit;
      }
    }
  }

  // --- Quitar artículo (solo BORRADOR)
  if ($action === 'remove_item') {
    if ($res['estado'] !== 'BORRADOR') {
      flash_set('err', "Solo podés modificar artículos en estado BORRADOR.");
      header("Location: reserva_editar.php?id={$id}");
      exit;
    }

    $det_id = (int)($_POST['det_id'] ?? 0);
    $del = $pdo->prepare("DELETE FROM reserva_detalle WHERE id=? AND reserva_id=?");
    $del->execute([$det_id, $id]);

    flash_set('ok', "Artículo removido.");
    header("Location: reserva_editar.php?id={$id}");
    exit;
  }

  // --- Agregar extra (solo BORRADOR)
  if ($action === 'add_extra') {
    if ($res['estado'] !== 'BORRADOR') {
      flash_set('err', "Solo podés modificar extras en estado BORRADOR.");
      header("Location: reserva_editar.php?id={$id}");
      exit;
    }

    $descripcion = normalize_text((string)($_POST['descripcion'] ?? ''));
    $proveedor   = normalize_text((string)($_POST['proveedor'] ?? ''));
    $cantidad    = (int)($_POST['cantidad'] ?? 0);
    $precio      = (float)($_POST['precio_unitario'] ?? 0);

    if ($descripcion === '') {
      $error = "La descripción del extra es obligatoria.";
    } elseif ($cantidad <= 0) {
      $error = "Cantidad inválida.";
    } elseif ($precio < 0) {
      $error = "El precio no puede ser negativo.";
    } else {
      $ins = $pdo->prepare("INSERT INTO reserva_extras
          (reserva_id, descripcion, proveedor, cantidad, precio_unitario)
          VALUES (?,?,?,?,?)");
      $ins->execute([
        $id,
        $descripcion,
        ($proveedor !== '' ? $proveedor : null),
        $cantidad,
        $precio
      ]);

      flash_set('ok', "Extra agregado.");
      header("Location: reserva_editar.php?id={$id}");
      exit;
    }
  }

  // --- Quitar extra (solo BORRADOR)
  if ($action === 'remove_extra') {
    if ($res['estado'] !== 'BORRADOR') {
      flash_set('err', "Solo podés modificar extras en estado BORRADOR.");
      header("Location: reserva_editar.php?id={$id}");
      exit;
    }

    $extra_id = (int)($_POST['extra_id'] ?? 0);
    $del = $pdo->prepare("DELETE FROM reserva_extras WHERE id=? AND reserva_id=?");
    $del->execute([$extra_id, $id]);

    flash_set('ok', "Extra removido.");
    header("Location: reserva_editar.php?id={$id}");
    exit;
  }

  // --- Cambiar estado (misma regla: ENTREGADA/DEVUELTA desde operación diaria)
  if ($action === 'set_status') {
    $to = (string)($_POST['to'] ?? '');

    $valid = ['BORRADOR','CONFIRMADA','ENTREGADA','DEVUELTA','CANCELADA'];
    if (!in_array($to, $valid, true)) {
      flash_set('err', "Estado inválido.");
      header("Location: reserva_editar.php?id={$id}");
      exit;
    }

    // 🔒 ENTREGADA y DEVUELTA solo desde Operación diaria
    if (in_array($to, ['ENTREGADA','DEVUELTA'], true)) {
      flash_set('err', "ENTREGADA/DEVUELTA se gestionan desde Operación diaria.");
      header("Location: reserva_operacion.php?id={$id}");
      exit;
    }

    $from = (string)$res['estado'];

    $allowed = [
      'BORRADOR'   => ['CONFIRMADA','CANCELADA'],
      'CONFIRMADA' => ['CANCELADA'],
      'ENTREGADA'  => [],
      'DEVUELTA'   => [],
      'CANCELADA'  => [],
    ];

    if (!in_array($to, $allowed[$from] ?? [], true)) {
      flash_set('err', "Transición no permitida ({$from} → {$to}).");
      header("Location: reserva_editar.php?id={$id}");
      exit;
    }

    // CONFIRMAR: debe tener items (inventario) o extras (permitimos confirmar solo con extras? -> mínimo: al menos 1 de ambos)
    if ($to === 'CONFIRMADA') {
      $items_now  = reserva_items($pdo, $id);
      $extras_now = reserva_extras($pdo, $id);

      if (!$items_now && !$extras_now) {
        flash_set('err', "No podés confirmar sin artículos o extras.");
        header("Location: reserva_editar.php?id={$id}");
        exit;
      }

      // Si hay items, revalidar disponibilidad
      if ($items_now) {
        $salida = (string)$res['fecha_salida'];
        $retorno = (string)$res['fecha_retorno'];

        foreach ($items_now as $it) {
          $articulo_id = (int)$it['articulo_id'];
          $qty = (int)$it['cantidad'];

          $stock = articulo_activo_stock($pdo, $articulo_id);
          $reservado = reserved_qty($pdo, $articulo_id, $salida, $retorno, $id);
          $disponible = $stock - $reservado;

          if ($qty > $disponible) {
            flash_set('err', "No hay disponibilidad para confirmar: {$it['codigo']} ({$it['nombre']}). Disponible: {$disponible}, requerido: {$qty}.");
            header("Location: reserva_editar.php?id={$id}");
            exit;
          }
        }
      }

      $pdo->beginTransaction();
      try {
        $up = $pdo->prepare("UPDATE reservas SET estado='CONFIRMADA' WHERE id=?");
        $up->execute([$id]);

        // Kardex informativo solo de items de inventario
        if ($items_now) {
          foreach ($items_now as $it) {
            $nota = "Reserva CONFIRMADA. Rango {$res['fecha_salida']} a {$res['fecha_retorno']}. Cantidad: {$it['cantidad']}.";
            add_mov(
              $pdo,
              'RESERVA',
              (int)$it['articulo_id'],
              $id,
              0,
              $nota,
              (int)current_user()['id']
            );
          }
        }

        $pdo->commit();
        flash_set('ok', "Reserva confirmada.");
        header("Location: reserva_editar.php?id={$id}");
        exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        flash_set('err', $e->getMessage());
        header("Location: reserva_editar.php?id={$id}");
        exit;
      }
    }

    if ($to === 'CANCELADA') {
      $up = $pdo->prepare("UPDATE reservas SET estado='CANCELADA' WHERE id=?");
      $up->execute([$id]);

      flash_set('ok', "Reserva cancelada.");
      header("Location: reserva_editar.php?id={$id}");
      exit;
    }

    flash_set('err', "Acción no aplicada.");
    header("Location: reserva_editar.php?id={$id}");
    exit;
  }
}

// recargar para mostrar
$res = get_reserva($pdo, $id);
$items = reserva_items($pdo, $id);
$extras = reserva_extras($pdo, $id);

$ok  = flash_get('ok');
$err = flash_get('err');

// Totales
$total_inv = 0.0;
foreach ($items as $it) {
  $total_inv += ((float)$it['precio_unitario']) * ((int)$it['cantidad']);
}

$total_ext = 0.0;
foreach ($extras as $ex) {
  $total_ext += ((float)$ex['subtotal']);
}

$total_gral = $total_inv + $total_ext;

require __DIR__ . '/../app/views/layout/header.php';
require __DIR__ . '/../app/views/layout/sidebar.php';
?>

<style>
  .card-soft { border-radius:14px; }
  .btn-pill { border-radius:10px; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>

<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <div class="text-muted small">Reserva</div>
    <h3 class="mb-1"><span class="mono"><?= htmlspecialchars((string)$res['codigo']) ?></span></h3>
    <div class="text-muted">
      <b><?= htmlspecialchars((string)$res['cliente']) ?></b> · <?= htmlspecialchars((string)$res['telefono']) ?><br>
      <span class="badge text-bg-dark">Salida: <?= htmlspecialchars((string)$res['fecha_salida']) ?></span>
      <span class="badge text-bg-secondary">Retorno: <?= htmlspecialchars((string)$res['fecha_retorno']) ?></span>
      <span class="badge text-bg-primary">Estado: <?= htmlspecialchars((string)$res['estado']) ?></span>
    </div>
  </div>

  <div class="d-flex gap-2">
    <a href="reservas.php" class="btn btn-outline-secondary btn-pill">Volver</a>
  </div>
</div>

<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3">
  <!-- Detalle -->
  <div class="col-12 col-lg-8">

    <!-- ARTÍCULOS -->
    <div class="card card-soft shadow-sm mb-3">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h5 class="mb-0">Artículos en la reserva</h5>
          <span class="small text-muted">* Solo editable en BORRADOR</span>
        </div>

        <?php if ($res['estado'] === 'BORRADOR'): ?>
          <form method="post" class="row g-2 mb-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_item">

            <div class="col-12 col-lg-8">
              <label class="form-label">Artículo</label>
              <select class="form-select" name="articulo_id" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($arts as $a): ?>
                  <option value="<?= (int)$a['id'] ?>">
                    <?= htmlspecialchars((string)$a['categoria']) ?> · <?= htmlspecialchars((string)$a['nombre']) ?>
                    (<?= htmlspecialchars((string)$a['codigo']) ?>)
                    [Stock activo: <?= (int)$a['cantidad_activa'] ?>]
                    [Q <?= number_format((float)$a['precio_unitario'], 2) ?>]
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-6 col-lg-2">
              <label class="form-label">Cantidad</label>
              <input type="number" min="1" name="cantidad" class="form-control" required>
            </div>

            <div class="col-6 col-lg-2 d-flex align-items-end">
              <button class="btn btn-primary btn-pill w-100">Agregar/Actualizar</button>
            </div>

            <div class="col-12">
              <div class="small text-muted">
                * Se valida disponibilidad real para el rango <?= htmlspecialchars((string)$res['fecha_salida']) ?> → <?= htmlspecialchars((string)$res['fecha_retorno']) ?>.
              </div>
            </div>
          </form>
        <?php endif; ?>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Código</th>
                <th>Artículo</th>
                <th>Categoría</th>
                <th>Cant.</th>
                <th>Precio</th>
                <th>Subtotal</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$items): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">Sin artículos.</td></tr>
              <?php endif; ?>

              <?php $n=0; foreach ($items as $it): $n++; ?>
                <?php
                  $sub = ((float)$it['precio_unitario']) * ((int)$it['cantidad']);
                ?>
                <tr>
                  <td><?= $n ?></td>
                  <td class="mono"><?= htmlspecialchars((string)$it['codigo']) ?></td>
                  <td><?= htmlspecialchars((string)$it['nombre']) ?></td>
                  <td><?= htmlspecialchars((string)$it['categoria']) ?></td>
                  <td><span class="badge text-bg-dark"><?= (int)$it['cantidad'] ?></span></td>
                  <td>Q <?= number_format((float)$it['precio_unitario'], 2) ?></td>
                  <td class="fw-semibold">Q <?= number_format($sub, 2) ?></td>
                  <td class="text-end">
                    <?php if ($res['estado'] === 'BORRADOR'): ?>
                      <form method="post" class="m-0" onsubmit="return confirm('¿Quitar artículo?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="det_id" value="<?= (int)$it['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger btn-pill">Quitar</button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted small">Bloqueado</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- EXTRAS -->
    <div class="card card-soft shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h5 class="mb-0">Extras / Servicios externos</h5>
          <span class="small text-muted">* Solo editable en BORRADOR</span>
        </div>

        <?php if ($res['estado'] === 'BORRADOR'): ?>
          <form method="post" class="row g-2 mb-3">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_extra">

            <div class="col-12 col-lg-6">
              <label class="form-label">Descripción</label>
              <input class="form-control" name="descripcion" required placeholder="Ej: Mantelería prestada, Inflable, Sonido...">
            </div>

            <div class="col-12 col-lg-3">
              <label class="form-label">Proveedor (opcional)</label>
              <input class="form-control" name="proveedor" placeholder="Ej: Alquifiestas X">
            </div>

            <div class="col-6 col-lg-1">
              <label class="form-label">Cant.</label>
              <input type="number" min="1" class="form-control" name="cantidad" value="1" required>
            </div>

            <div class="col-6 col-lg-2">
              <label class="form-label">Precio (Q)</label>
              <input type="number" min="0" step="0.01" class="form-control" name="precio_unitario" value="0.00" required>
            </div>

            <div class="col-12">
              <button class="btn btn-outline-primary btn-pill">Agregar extra</button>
            </div>

            <div class="col-12">
              <div class="small text-muted">
                * Los extras NO afectan inventario ni kardex, pero sí entran en la cotización.
              </div>
            </div>
          </form>
        <?php endif; ?>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Descripción</th>
                <th>Proveedor</th>
                <th>Cant.</th>
                <th>Precio</th>
                <th>Subtotal</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$extras): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">Sin extras.</td></tr>
              <?php endif; ?>

              <?php $n=0; foreach ($extras as $ex): $n++; ?>
                <tr>
                  <td><?= $n ?></td>
                  <td class="fw-semibold"><?= htmlspecialchars((string)$ex['descripcion']) ?></td>
                  <td class="text-muted"><?= htmlspecialchars((string)($ex['proveedor'] ?? '')) ?></td>
                  <td><span class="badge text-bg-dark"><?= (int)$ex['cantidad'] ?></span></td>
                  <td>Q <?= number_format((float)$ex['precio_unitario'], 2) ?></td>
                  <td class="fw-semibold">Q <?= number_format((float)$ex['subtotal'], 2) ?></td>
                  <td class="text-end">
                    <?php if ($res['estado'] === 'BORRADOR'): ?>
                      <form method="post" class="m-0" onsubmit="return confirm('¿Quitar extra?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="remove_extra">
                        <input type="hidden" name="extra_id" value="<?= (int)$ex['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger btn-pill">Quitar</button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted small">Bloqueado</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

  </div>

  <!-- Acciones / Totales -->
  <div class="col-12 col-lg-4">
    <div class="card card-soft shadow-sm mb-3">
      <div class="card-body p-4">
        <h5 class="mb-3">Totales</h5>

        <div class="d-flex justify-content-between">
          <div class="text-muted">Inventario</div>
          <div class="fw-semibold">Q <?= number_format($total_inv, 2) ?></div>
        </div>
        <div class="d-flex justify-content-between">
          <div class="text-muted">Extras</div>
          <div class="fw-semibold">Q <?= number_format($total_ext, 2) ?></div>
        </div>

        <hr>

        <div class="d-flex justify-content-between">
          <div class="fw-semibold">Total</div>
          <div class="fw-bold fs-5">Q <?= number_format($total_gral, 2) ?></div>
        </div>

        <div class="small text-muted mt-2">
          * Total = (artículos * precio_unitario) + (extras).
        </div>
      </div>
    </div>

    <div class="card card-soft shadow-sm">
      <div class="card-body p-4">
        <h5 class="mb-3">Acciones</h5>

        <div class="alert alert-info small">
          Flujo: <b>BORRADOR → CONFIRMADA → ENTREGADA → DEVUELTA</b><br>
          Cancelación: <b>BORRADOR/CONFIRMADA → CANCELADA</b>
        </div>

        <?php if ($res['estado'] === 'BORRADOR'): ?>
  <a class="btn btn-outline-dark btn-pill w-100 mb-2" target="_blank"
     href="reserva_cotizacion.php?id=<?= (int)$id ?>">
    Ver / Imprimir Cotización
  </a>
<?php endif; ?>

<?php if (in_array($res['estado'], ['CONFIRMADA','ENTREGADA','DEVUELTA'], true)): ?>
  <a class="btn btn-outline-dark btn-pill w-100 mb-2" target="_blank"
     href="reserva_nota_entrega.php?id=<?= (int)$id ?>">
    Ver / Imprimir Nota de entrega
  </a>
<?php endif; ?>


        <a class="btn btn-outline-primary btn-pill w-100 mb-2" href="reserva_operacion.php?id=<?= (int)$id ?>">
          Operación diaria (entrega/devolución)
        </a>

        <form method="post" class="d-grid gap-2">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="set_status">

          <?php if ($res['estado'] === 'BORRADOR'): ?>
            <button class="btn btn-success btn-pill" name="to" value="CONFIRMADA"
              onclick="return confirm('¿Confirmar reserva? Se validará disponibilidad.');">
              Confirmar
            </button>

            <button class="btn btn-outline-danger btn-pill" name="to" value="CANCELADA"
              onclick="return confirm('¿Cancelar reserva?');">
              Cancelar
            </button>

          <?php elseif ($res['estado'] === 'CONFIRMADA' || $res['estado'] === 'ENTREGADA'): ?>
            <div class="alert alert-warning small mb-2">
              La entrega/devolución se gestiona desde <b>Operación diaria</b>.
            </div>

            <?php if ($res['estado'] === 'CONFIRMADA'): ?>
              <button class="btn btn-outline-danger btn-pill" name="to" value="CANCELADA"
                onclick="return confirm('¿Cancelar reserva confirmada?');">
                Cancelar
              </button>
            <?php else: ?>
              <button class="btn btn-outline-secondary btn-pill" disabled>Sin acciones aquí</button>
            <?php endif; ?>

          <?php else: ?>
            <div class="text-muted">No hay acciones disponibles en este estado.</div>
          <?php endif; ?>

        </form>

        <hr>
        <div class="small text-muted">
          * Disponibilidad se bloquea con reservas <b>CONFIRMADAS</b> y <b>ENTREGADAS</b> que se traslapan en fechas.
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>
