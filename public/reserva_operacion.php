<?php
declare(strict_types=1);

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

function get_detalle(PDO $pdo, int $reserva_id): array {
  $st = $pdo->prepare("SELECT d.id, d.articulo_id, d.cantidad, d.entregado, d.devuelto, d.danado, d.perdido,
                              a.codigo, a.nombre, a.cantidad_total, a.cantidad_activa,
                              c.nombre AS categoria
                       FROM reserva_detalle d
                       INNER JOIN articulos a ON a.id=d.articulo_id
                       INNER JOIN categorias c ON c.id=a.categoria_id
                       WHERE d.reserva_id=?
                       ORDER BY c.nombre ASC, a.nombre ASC");
  $st->execute([$reserva_id]);
  return $st->fetchAll();
}

/**
 * OJO: requiere columnas entregado/devuelto/obs_* en reserva_extras (ver ALTER TABLE arriba)
 */
function get_extras(PDO $pdo, int $reserva_id): array {
  $st = $pdo->prepare("SELECT e.id, e.reserva_id, e.descripcion, e.proveedor,
                              e.cantidad, e.precio_unitario, e.subtotal,
                              e.entregado, e.devuelto, e.obs_entrega, e.obs_bodega,
                              e.creado_en
                       FROM reserva_extras e
                       WHERE e.reserva_id=?
                       ORDER BY e.id ASC");
  $st->execute([$reserva_id]);
  return $st->fetchAll();
}

function add_mov(PDO $pdo, string $tipo, int $articulo_id, int $reserva_id, int $cantidad, string $nota, int $user_id): void {
  $st = $pdo->prepare("INSERT INTO movimientos_inventario
    (tipo, articulo_id, referencia_tipo, referencia_id, cantidad, nota, creado_por)
    VALUES (?, ?, 'RESERVA', ?, ?, ?, ?)");
  $st->execute([$tipo, $articulo_id, $reserva_id, $cantidad, $nota, $user_id]);
}

function set_estado(PDO $pdo, int $reserva_id, string $estado): void {
  $st = $pdo->prepare("UPDATE reservas SET estado=? WHERE id=?");
  $st->execute([$estado, $reserva_id]);
}

function all_delivered_inv(array $items): bool {
  foreach ($items as $it) {
    if ((int)$it['entregado'] < (int)$it['cantidad']) return false;
  }
  return true;
}

function all_delivered_extras(array $extras): bool {
  foreach ($extras as $e) {
    if ((int)$e['entregado'] < (int)$e['cantidad']) return false;
  }
  return true;
}

function all_closed_inv(array $items): bool {
  foreach ($items as $it) {
    $cerrado = (int)$it['devuelto'] + (int)$it['danado'] + (int)$it['perdido'];
    if ($cerrado < (int)$it['entregado']) return false;
  }
  return true;
}

function all_closed_extras(array $extras): bool {
  foreach ($extras as $e) {
    // Extras “cerrado” = devuelto (de lo entregado)
    if ((int)$e['devuelto'] < (int)$e['entregado']) return false;
  }
  return true;
}

function money(float $n): string {
  return 'Q ' . number_format($n, 2, '.', ',');
}

/** Para el modal: lista de artículos activos */
function get_articulos_activos(PDO $pdo): array {
  $st = $pdo->query("SELECT a.id, a.codigo, a.nombre, a.cantidad_activa, c.nombre AS categoria
                     FROM articulos a
                     INNER JOIN categorias c ON c.id=a.categoria_id
                     WHERE a.estado='ACTIVO'
                     ORDER BY c.nombre ASC, a.nombre ASC");
  return $st->fetchAll();
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo "ID inválido."; exit; }

$res = get_reserva($pdo, $id);
if (!$res) { echo "Reserva no encontrada."; exit; }

$estado = (string)$res['estado'];
if (!in_array($estado, ['CONFIRMADA','ENTREGADA','DEVUELTA','CANCELADA'], true)) {
  flash_set('err', "La operación diaria aplica cuando la reserva está CONFIRMADA o ENTREGADA.");
  header("Location: reserva_editar.php?id={$id}");
  exit;
}

$error = null;

// ================== POST ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? null)) {
    flash_set('err', "Token inválido. Recargá.");
    header("Location: reserva_operacion.php?id={$id}");
    exit;
  }

  $action = (string)($_POST['action'] ?? '');

  // Recargar datos para operar
  $items  = get_detalle($pdo, $id);
  $extras = get_extras($pdo, $id);

  // ---------------- ENTREGAR NORMAL (inventario + extras) ----------------
  if ($action === 'entregar') {
    if ($estado !== 'CONFIRMADA') {
      flash_set('err', "Solo podés registrar entregas cuando está CONFIRMADA.");
      header("Location: reserva_operacion.php?id={$id}");
      exit;
    }

    $pdo->beginTransaction();
    $pdo->exec("SET innodb_lock_wait_timeout = 5");
    try {
      // Inventario
      foreach ($items as $it) {
        $det_id = (int)$it['id'];
        $key = "entrega_{$det_id}";
        $add = (int)($_POST[$key] ?? 0);
        if ($add <= 0) continue;

        $pendiente = (int)$it['cantidad'] - (int)$it['entregado'];
        if ($add > $pendiente) {
          throw new RuntimeException("Entrega excede pendiente en {$it['codigo']} ({$it['nombre']}). Pendiente: {$pendiente}.");
        }

        $up = $pdo->prepare("UPDATE reserva_detalle SET entregado = entregado + ? WHERE id=? AND reserva_id=?");
        $up->execute([$add, $det_id, $id]);

        $nota = "Entrega parcial. Reserva {$res['codigo']}.";
        add_mov($pdo, 'SALIDA', (int)$it['articulo_id'], $id, -$add, $nota, (int)current_user()['id']);
      }

      // Extras
      foreach ($extras as $e) {
        $eid = (int)$e['id'];
        $key = "entrega_extra_{$eid}";
        $add = (int)($_POST[$key] ?? 0);
        if ($add <= 0) continue;

        $pendiente = (int)$e['cantidad'] - (int)$e['entregado'];
        if ($add > $pendiente) {
          throw new RuntimeException("Entrega excede pendiente en EXTRA: {$e['descripcion']}. Pendiente: {$pendiente}.");
        }

        $up = $pdo->prepare("UPDATE reserva_extras SET entregado = entregado + ? WHERE id=? AND reserva_id=?");
        $up->execute([$add, $eid, $id]);
      }

      // Estado
      $items2  = get_detalle($pdo, $id);
      $extras2 = get_extras($pdo, $id);
      if (all_delivered_inv($items2) && all_delivered_extras($extras2)) {
        set_estado($pdo, $id, 'ENTREGADA');
      }

      $pdo->commit();
      flash_set('ok', "Entrega registrada.");
      header("Location: reserva_operacion.php?id={$id}");
      exit;

    } catch (Throwable $e) {
      $pdo->rollBack();
      $error = $e->getMessage();
    }
  }

  // ---------------- DEVOLVER NORMAL (inventario + extras) ----------------
  if ($action === 'devolver') {
    if ($estado !== 'ENTREGADA') {
      flash_set('err', "Solo podés registrar devoluciones cuando está ENTREGADA.");
      header("Location: reserva_operacion.php?id={$id}");
      exit;
    }

    $obs_bodega = trim((string)($_POST['obs_bodega'] ?? ''));

    $pdo->beginTransaction();
    $pdo->exec("SET innodb_lock_wait_timeout = 5");
    try {
      // Inventario
      foreach ($items as $it) {
        $det_id = (int)$it['id'];

        $k_dev = "dev_{$det_id}";
        $k_dan = "dan_{$det_id}";
        $k_per = "per_{$det_id}";

        $add_dev = (int)($_POST[$k_dev] ?? 0);
        $add_dan = (int)($_POST[$k_dan] ?? 0);
        $add_per = (int)($_POST[$k_per] ?? 0);

        if ($add_dev < 0 || $add_dan < 0 || $add_per < 0) {
          throw new RuntimeException("No se permiten valores negativos.");
        }

        $add_total = $add_dev + $add_dan + $add_per;
        if ($add_total === 0) continue;

        $cerrado = (int)$it['devuelto'] + (int)$it['danado'] + (int)$it['perdido'];
        $pendiente = (int)$it['entregado'] - $cerrado;

        if ($add_total > $pendiente) {
          throw new RuntimeException("Devolución/daño/pérdida excede pendiente en {$it['codigo']} ({$it['nombre']}). Pendiente: {$pendiente}.");
        }

        $up = $pdo->prepare("UPDATE reserva_detalle
                             SET devuelto = devuelto + ?,
                                 danado  = danado  + ?,
                                 perdido = perdido + ?
                             WHERE id=? AND reserva_id=?");
        $up->execute([$add_dev, $add_dan, $add_per, $det_id, $id]);

        if ($add_dev > 0) {
          $nota = "Devolución parcial. Reserva {$res['codigo']}.";
          if ($obs_bodega !== '') $nota .= " Obs bodega: {$obs_bodega}";
          add_mov($pdo, 'DEVOLUCION', (int)$it['articulo_id'], $id, $add_dev, $nota, (int)current_user()['id']);
        }

        if ($add_dan > 0) {
          $st = $pdo->prepare("UPDATE articulos
                               SET cantidad_activa = GREATEST(cantidad_activa - ?, 0)
                               WHERE id=?");
          $st->execute([$add_dan, (int)$it['articulo_id']]);

          $nota = "Daño en devolución. Reserva {$res['codigo']}. Baja en stock ACTIVO.";
          if ($obs_bodega !== '') $nota .= " Obs bodega: {$obs_bodega}";
          add_mov($pdo, 'AJUSTE', (int)$it['articulo_id'], $id, -$add_dan, $nota, (int)current_user()['id']);
        }

        if ($add_per > 0) {
          $st = $pdo->prepare("UPDATE articulos
                               SET cantidad_total  = GREATEST(cantidad_total - ?, 0),
                                   cantidad_activa = GREATEST(cantidad_activa - ?, 0)
                               WHERE id=?");
          $st->execute([$add_per, $add_per, (int)$it['articulo_id']]);

          $nota = "Pérdida en devolución. Reserva {$res['codigo']}. Baja en stock TOTAL y ACTIVO.";
          if ($obs_bodega !== '') $nota .= " Obs bodega: {$obs_bodega}";
          add_mov($pdo, 'AJUSTE', (int)$it['articulo_id'], $id, -$add_per, $nota, (int)current_user()['id']);
        }
      }

      // Extras
      foreach ($extras as $e) {
        $eid = (int)$e['id'];
        $k_dev = "dev_extra_{$eid}";
        $k_obs = "obs_extra_{$eid}";

        $add_dev = (int)($_POST[$k_dev] ?? 0);
        $obs = trim((string)($_POST[$k_obs] ?? ''));

        if ($add_dev < 0) throw new RuntimeException("No se permiten negativos (extras).");
        if ($add_dev === 0 && $obs === '') continue;

        $pend = (int)$e['entregado'] - (int)$e['devuelto'];
        if ($add_dev > $pend) {
          throw new RuntimeException("Devolución excede pendiente en EXTRA: {$e['descripcion']}. Pendiente: {$pend}.");
        }

        if ($add_dev > 0) {
          $up = $pdo->prepare("UPDATE reserva_extras SET devuelto = devuelto + ? WHERE id=? AND reserva_id=?");
          $up->execute([$add_dev, $eid, $id]);
        }

        if ($obs !== '') {
          $up2 = $pdo->prepare("UPDATE reserva_extras SET obs_bodega = ? WHERE id=? AND reserva_id=?");
          $up2->execute([$obs, $eid, $id]);
        }
      }

      // Estado final
      $items2  = get_detalle($pdo, $id);
      $extras2 = get_extras($pdo, $id);

      if (all_closed_inv($items2) && all_closed_extras($extras2)) {
        set_estado($pdo, $id, 'DEVUELTA');
      }

      $pdo->commit();
      flash_set('ok', "Devolución registrada.");
      header("Location: reserva_operacion.php?id={$id}");
      exit;

    } catch (Throwable $e) {
      $pdo->rollBack();
      $error = $e->getMessage();
    }
  }

  // ---------------- ENTREGA ADICIONAL (MODAL) - SOLO ADMIN ----------------
  if ($action === 'entrega_adicional') {
    if (!is_admin()) {
      flash_set('err', "Solo ADMIN puede registrar entregas adicionales.");
      header("Location: reserva_operacion.php?id={$id}");
      exit;
    }
    if (!in_array($estado, ['CONFIRMADA','ENTREGADA'], true)) {
      flash_set('err', "Entrega adicional solo en CONFIRMADA o ENTREGADA.");
      header("Location: reserva_operacion.php?id={$id}");
      exit;
    }

    $tipo = (string)($_POST['tipo'] ?? '');

    $pdo->beginTransaction();
    $pdo->exec("SET innodb_lock_wait_timeout = 5");
    try {
      if ($tipo === 'INV') {
        $articulo_id = (int)($_POST['articulo_id'] ?? 0);
        $cant = (int)($_POST['cantidad_inv'] ?? 0);
        if ($articulo_id <= 0 || $cant <= 0) throw new RuntimeException("Seleccioná un artículo y cantidad válida.");

        // 🔒 Lock + validar stock + descontar activo
        $stLock = $pdo->prepare("SELECT cantidad_activa FROM articulos WHERE id=? FOR UPDATE");
        $stLock->execute([$articulo_id]);
        $artRow = $stLock->fetch();
        if (!$artRow) throw new RuntimeException("Artículo no encontrado.");

        $disp = (int)$artRow['cantidad_activa'];
        if ($cant > $disp) throw new RuntimeException("Stock insuficiente. Disponible: {$disp}.");

        $stUpd = $pdo->prepare("UPDATE articulos SET cantidad_activa = cantidad_activa - ? WHERE id=?");
        $stUpd->execute([$cant, $articulo_id]);

        // upsert a reserva_detalle
        $stx = $pdo->prepare("SELECT id FROM reserva_detalle WHERE reserva_id=? AND articulo_id=? LIMIT 1");
        $stx->execute([$id, $articulo_id]);
        $ex = $stx->fetch();

        if ($ex) {
          $det_id = (int)$ex['id'];
          $up = $pdo->prepare("UPDATE reserva_detalle
                               SET cantidad = cantidad + ?, entregado = entregado + ?
                               WHERE id=? AND reserva_id=?");
          $up->execute([$cant, $cant, $det_id, $id]);
        } else {
          $ins = $pdo->prepare("INSERT INTO reserva_detalle (reserva_id, articulo_id, cantidad, entregado, devuelto, danado, perdido)
                                VALUES (?,?,?,?,0,0,0)");
          $ins->execute([$id, $articulo_id, $cant, $cant]);
        }

        $nota = "Entrega adicional (ADMIN). Reserva {$res['codigo']}.";
        add_mov($pdo, 'SALIDA', $articulo_id, $id, -$cant, $nota, (int)current_user()['id']);
      }

      if ($tipo === 'EXT') {
        $desc = trim((string)($_POST['descripcion'] ?? ''));
        $prov = trim((string)($_POST['proveedor'] ?? ''));
        $cant = (int)($_POST['cantidad_ext'] ?? 0);
        $precio = (float)($_POST['precio_unitario'] ?? 0);

        if ($desc === '' || $cant <= 0) throw new RuntimeException("Descripción y cantidad son obligatorias.");
        if ($precio < 0) $precio = 0;

        $sub = $cant * $precio;

        $ins = $pdo->prepare("INSERT INTO reserva_extras
          (reserva_id, descripcion, proveedor, cantidad, precio_unitario, subtotal, entregado, devuelto, obs_entrega, obs_bodega)
          VALUES (?,?,?,?,?,?,?,?,?,?)");
        $ins->execute([$id, $desc, ($prov !== '' ? $prov : null), $cant, $precio, $sub, $cant, 0, null, null]);
      }

      $items2  = get_detalle($pdo, $id);
      $extras2 = get_extras($pdo, $id);
      if ($estado === 'CONFIRMADA' && all_delivered_inv($items2) && all_delivered_extras($extras2)) {
        set_estado($pdo, $id, 'ENTREGADA');
      }

      $pdo->commit();
      flash_set('ok', "Entrega adicional guardada.");
      header("Location: reserva_operacion.php?id={$id}");
      exit;

    } catch (Throwable $e) {
      $pdo->rollBack();
      $error = $e->getMessage();
    }
  }
}

// ================== CARGA FINAL ==================
$res = get_reserva($pdo, $id);
$estado = (string)$res['estado'];
$items  = get_detalle($pdo, $id);
$extras = get_extras($pdo, $id);
$arts   = get_articulos_activos($pdo);

$ok  = flash_get('ok');
$err = flash_get('err');

require __DIR__ . '/../app/views/layout/header.php';
require __DIR__ . '/../app/views/layout/sidebar.php';
?>

<style>
  .card-soft{border-radius:14px;}
  .btn-pill{border-radius:10px;}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;}
</style>

<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <div class="text-muted small">Operación diaria</div>
    <h3 class="mb-1">Reserva <span class="mono"><?= htmlspecialchars((string)$res['codigo']) ?></span></h3>
    <div class="text-muted">
      <b><?= htmlspecialchars((string)$res['cliente']) ?></b> · <?= htmlspecialchars((string)$res['telefono']) ?><br>
      <span class="badge text-bg-dark">Salida: <?= htmlspecialchars((string)$res['fecha_salida']) ?></span>
      <span class="badge text-bg-secondary">Retorno: <?= htmlspecialchars((string)$res['fecha_retorno']) ?></span>
      <span class="badge text-bg-primary">Estado: <?= htmlspecialchars((string)$res['estado']) ?></span>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="reserva_editar.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-pill">Volver a detalle</a>
    <a href="reservas.php" class="btn btn-outline-secondary btn-pill">Reservas</a>
  </div>
</div>

<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card card-soft shadow-sm">
      <div class="card-body p-4">
        <h5 class="mb-3">Checklist</h5>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>Tipo</th>
                <th>Detalle</th>
                <th>Cant.</th>
                <th>Entregado</th>
                <th>Cerrado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <?php $cerrado = (int)$it['devuelto'] + (int)$it['danado'] + (int)$it['perdido']; ?>
                <tr>
                  <td><span class="badge rounded-pill text-bg-secondary">Inventario</span></td>
                  <td>
                    <div class="fw-semibold"><span class="mono"><?= htmlspecialchars((string)$it['codigo']) ?></span> · <?= htmlspecialchars((string)$it['nombre']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars((string)$it['categoria']) ?></div>
                  </td>
                  <td><span class="badge text-bg-dark"><?= (int)$it['cantidad'] ?></span></td>
                  <td><span class="badge text-bg-secondary"><?= (int)$it['entregado'] ?></span></td>
                  <td><span class="badge text-bg-info"><?= $cerrado ?></span></td>
                </tr>
              <?php endforeach; ?>

              <?php foreach ($extras as $e): ?>
                <?php $cerrado = (int)$e['devuelto']; ?>
                <tr>
                  <td><span class="badge rounded-pill text-bg-dark">Extra</span></td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars((string)$e['descripcion']) ?></div>
                    <?php if (!empty($e['proveedor'])): ?>
                      <div class="text-muted small">Proveedor: <?= htmlspecialchars((string)$e['proveedor']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge text-bg-dark"><?= (int)$e['cantidad'] ?></span></td>
                  <td><span class="badge text-bg-secondary"><?= (int)$e['entregado'] ?></span></td>
                  <td><span class="badge text-bg-info"><?= $cerrado ?></span></td>
                </tr>
              <?php endforeach; ?>

              <?php if (!$items && !$extras): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">Sin artículos ni extras.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="small text-muted">
          * Inventario “Cerrado” = Devuelto + Dañado + Perdido (de lo entregado).<br>
          * Extras “Cerrado” = Devuelto (de lo entregado) + observación opcional.
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <?php if ($estado === 'CONFIRMADA'): ?>
      <div class="card card-soft shadow-sm mb-3">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">Registrar entrega</h5>

            <?php if (is_admin()): ?>
              <button type="button" class="btn btn-sm btn-outline-primary btn-pill"
                      data-bs-toggle="modal" data-bs-target="#modalEntregaAdicional">
                + Entrega adicional
              </button>
            <?php endif; ?>
          </div>

          <form method="post" class="d-grid gap-2">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="entregar">

            <div class="fw-semibold mt-2">Inventario</div>
            <?php foreach ($items as $it): ?>
              <?php $pend = (int)$it['cantidad'] - (int)$it['entregado']; ?>
              <label class="form-label mb-1">
                <?= htmlspecialchars((string)$it['codigo']) ?> (pendiente: <?= $pend ?>)
              </label>
              <input class="form-control mb-2" type="number" min="0" max="<?= $pend ?>"
                     name="entrega_<?= (int)$it['id'] ?>" value="0">
            <?php endforeach; ?>

            <div class="fw-semibold mt-2">Extras</div>
            <?php foreach ($extras as $e): ?>
              <?php $pend = (int)$e['cantidad'] - (int)$e['entregado']; ?>
              <label class="form-label mb-1">
                <?= htmlspecialchars((string)$e['descripcion']) ?> (pendiente: <?= $pend ?>)
              </label>
              <input class="form-control mb-2" type="number" min="0" max="<?= $pend ?>"
                     name="entrega_extra_<?= (int)$e['id'] ?>" value="0">
            <?php endforeach; ?>

            <button class="btn btn-success btn-pill"
              onclick="return confirm('¿Registrar entrega? Esto generará SALIDAS en kardex para inventario.');">
              Guardar entrega
            </button>
          </form>

          <div class="small text-muted mt-2">
            * Cuando se entregue TODO (inventario + extras), cambia automáticamente a <b>ENTREGADA</b>.
          </div>
        </div>
      </div>

    <?php elseif ($estado === 'ENTREGADA'): ?>
      <div class="card card-soft shadow-sm">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">Registrar devolución</h5>

            <?php if (is_admin()): ?>
              <button type="button" class="btn btn-sm btn-outline-primary btn-pill"
                      data-bs-toggle="modal" data-bs-target="#modalEntregaAdicional">
                + Entrega adicional
              </button>
            <?php endif; ?>
          </div>

          <form method="post" class="d-grid gap-2">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="devolver">

            <div class="fw-semibold">Inventario</div>
            <?php foreach ($items as $it): ?>
              <?php
                $cerrado = (int)$it['devuelto'] + (int)$it['danado'] + (int)$it['perdido'];
                $pend = (int)$it['entregado'] - $cerrado;
              ?>
              <div class="border rounded-3 p-2 mb-2 bg-light">
                <div class="fw-semibold"><?= htmlspecialchars((string)$it['codigo']) ?> · pendiente: <?= $pend ?></div>
                <div class="row g-2 mt-1">
                  <div class="col-4">
                    <label class="form-label small">Devuelto</label>
                    <input class="form-control" type="number" min="0" max="<?= $pend ?>" name="dev_<?= (int)$it['id'] ?>" value="0">
                  </div>
                  <div class="col-4">
                    <label class="form-label small">Dañado</label>
                    <input class="form-control" type="number" min="0" max="<?= $pend ?>" name="dan_<?= (int)$it['id'] ?>" value="0">
                  </div>
                  <div class="col-4">
                    <label class="form-label small">Perdido</label>
                    <input class="form-control" type="number" min="0" max="<?= $pend ?>" name="per_<?= (int)$it['id'] ?>" value="0">
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

            <div class="border rounded-3 p-3 bg-white">
              <label class="form-label fw-semibold">Observación bodega (opcional)</label>
              <textarea class="form-control" name="obs_bodega" rows="2"
                        placeholder="Ej: manteles manchados, servilletas húmedas, faltan piezas..."></textarea>
              <div class="small text-muted mt-1">
                * Esta observación se agrega a notas de kardex cuando corresponde.
              </div>
            </div>

            <hr>

            <div class="fw-semibold">Extras</div>
            <?php foreach ($extras as $e): ?>
              <?php $pend = (int)$e['entregado'] - (int)$e['devuelto']; ?>
              <div class="border rounded-3 p-2 mb-2 bg-light">
                <div class="fw-semibold"><?= htmlspecialchars((string)$e['descripcion']) ?> · pendiente: <?= $pend ?></div>
                <div class="row g-2 mt-1">
                  <div class="col-4">
                    <label class="form-label small">Devuelto</label>
                    <input class="form-control" type="number" min="0" max="<?= $pend ?>" name="dev_extra_<?= (int)$e['id'] ?>" value="0">
                  </div>
                  <div class="col-8">
                    <label class="form-label small">Observación (opcional)</label>
                    <input class="form-control" name="obs_extra_<?= (int)$e['id'] ?>"
                           placeholder="Ej: se devolvió manchado, roto, incompleto...">
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

            <button class="btn btn-primary btn-pill"
              onclick="return confirm('¿Registrar devolución/daños/pérdidas?');">
              Guardar devolución
            </button>
          </form>

          <div class="small text-muted mt-2">
            * Inventario: Devuelto genera <b>DEVOLUCION</b> en kardex. Daño/Pérdida generan <b>AJUSTE</b> y bajan stock.<br>
            * Cuando todo esté cerrado (inventario + extras), cambia a <b>DEVUELTA</b>.
          </div>
        </div>
      </div>

    <?php else: ?>
      <div class="card card-soft shadow-sm">
        <div class="card-body p-4">
          <h5 class="mb-2">Sin acciones</h5>
          <div class="text-muted">Estado actual: <b><?= htmlspecialchars($estado) ?></b></div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ================= MODAL ENTREGA ADICIONAL (ADMIN) ================= -->
<?php if (is_admin() && in_array($estado, ['CONFIRMADA','ENTREGADA'], true)): ?>
<div class="modal fade" id="modalEntregaAdicional" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px;">
      <div class="modal-header">
        <h5 class="modal-title">Entrega adicional (solo ADMIN)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <form method="post" class="modal-body">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="entrega_adicional">

        <div class="alert alert-info small">
          Esto registra un “extra de último momento” en la MISMA reserva y lo marca como <b>entregado</b>.
          Para inventario además genera <b>SALIDA</b> en kardex.
        </div>

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Tipo</label>
            <select class="form-select" name="tipo" id="tipoEntregaAdicional" required>
              <option value="INV" selected>Inventario (bodega)</option>
              <option value="EXT">Extra / servicio externo</option>
            </select>
          </div>

          <!-- INVENTARIO -->
          <div class="col-12" id="wrapINV">
            <div class="row g-3">
              <div class="col-12 col-lg-8">
                <label class="form-label fw-semibold">Artículo</label>
                <select class="form-select" name="articulo_id">
                  <option value="">-- Seleccionar --</option>
                  <?php foreach ($arts as $a): ?>
                    <option value="<?= (int)$a['id'] ?>">
                      <?= htmlspecialchars((string)$a['categoria']) ?> · <?= htmlspecialchars((string)$a['nombre']) ?>
                      (<?= htmlspecialchars((string)$a['codigo']) ?>) [Stock activo: <?= (int)$a['cantidad_activa'] ?>]
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-lg-4">
                <label class="form-label fw-semibold">Cantidad adicional</label>
                <input class="form-control" type="number" min="1" name="cantidad_inv" value="1">
              </div>

              <div class="col-12">
                <div class="small text-muted">
                  * Esto aumenta la cantidad de la reserva y lo deja como ENTREGADO (SALIDA en kardex).
                </div>
              </div>
            </div>
          </div>

          <!-- EXTRAS -->
          <div class="col-12 d-none" id="wrapEXT">
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold">Descripción</label>
                <input class="form-control" name="descripcion" placeholder="Ej: Manteles prestados, transporte, meseros...">
              </div>

              <div class="col-12 col-lg-6">
                <label class="form-label fw-semibold">Proveedor (opcional)</label>
                <input class="form-control" name="proveedor" placeholder="Ej: Alquifiesta X">
              </div>

              <div class="col-6 col-lg-3">
                <label class="form-label fw-semibold">Cantidad</label>
                <input class="form-control" type="number" min="1" name="cantidad_ext" value="1">
              </div>

              <div class="col-6 col-lg-3">
                <label class="form-label fw-semibold">Precio (Q)</label>
                <input class="form-control" type="number" step="0.01" min="0" name="precio_unitario" value="0.00">
              </div>

              <div class="col-12">
                <div class="small text-muted">
                  * Se crea un EXTRA y se marca como ENTREGADO (no toca kardex de inventario).
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer px-0 pb-0 mt-3">
          <button type="button" class="btn btn-outline-secondary btn-pill" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary btn-pill" type="submit">Guardar entrega adicional</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function(){
    const sel = document.getElementById('tipoEntregaAdicional');
    const inv = document.getElementById('wrapINV');
    const ext = document.getElementById('wrapEXT');

    function toggle(){
      const v = sel.value;
      if (v === 'EXT') {
        inv.classList.add('d-none');
        ext.classList.remove('d-none');
      } else {
        ext.classList.add('d-none');
        inv.classList.remove('d-none');
      }
    }
    sel.addEventListener('change', toggle);
    toggle();
  })();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>
