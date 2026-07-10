<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

start_app_session();
require_login();
$pdo = db();

/**
 * Genera el siguiente código de reserva correlativo
 */
function next_res_code(PDO $pdo): string {
  $st = $pdo->query("SELECT id FROM reservas ORDER BY id DESC LIMIT 1");
  $row = $st->fetch();
  $next = $row ? ((int)$row['id'] + 1) : 1;
  return 'RES-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

/**
 * Obtiene listado de clientes activos
 */
function get_active_clients(PDO $pdo): array {
  $st = $pdo->query("SELECT id, nombres, apellidos, telefono
                       FROM clientes
                       WHERE estado='ACTIVO'
                       ORDER BY nombres ASC, apellidos ASC");
  return $st->fetchAll();
}

$error = null;
$hoy = date('Y-m-d'); // Fecha actual para validaciones

// --- ACCIÓN: CREAR RESERVA (CABECERA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? null)) {
    flash_set('err', "Token inválido. Recargá la página.");
    header('Location: reservas.php');
    exit;
  }

  $cliente_id = (int)($_POST['cliente_id'] ?? 0);
  $fecha_salida = (string)($_POST['fecha_salida'] ?? '');
$fecha_evento = (string)($_POST['fecha_evento'] ?? '');
$fecha_retorno = (string)($_POST['fecha_retorno'] ?? '');
  $nota = trim((string)($_POST['nota'] ?? ''));

  // VALIDACIONES DE NEGOCIO
  if ($cliente_id <= 0) {
    $error = "Seleccioná un cliente.";
  } elseif ($fecha_salida === '' || $fecha_evento === '' || $fecha_retorno === '') {
  $error = "Las fechas de salida, evento y retorno son obligatorias.";
  } 
  // Bloqueo de fechas pasadas
  elseif ($fecha_salida < $hoy) {
    $error = "No puedes crear reservas con fecha de salida anterior a hoy ($hoy).";
  }

  elseif (strtotime($fecha_evento) < strtotime($fecha_salida)) {
  $error = "La fecha del evento no puede ser menor a la fecha de salida.";
}
elseif (strtotime($fecha_retorno) < strtotime($fecha_evento)) {
  $error = "La fecha de retorno no puede ser menor a la fecha del evento.";
}

  // Validación de orden cronológico
  else {
    $codigo = next_res_code($pdo);

    $ins = $pdo->prepare("INSERT INTO reservas
  (codigo, cliente_id, fecha_salida, fecha_evento, fecha_retorno, estado, nota, creado_por)
  VALUES (?,?,?,?,?, 'BORRADOR', ?, ?)");
    $ins->execute([
      $codigo,
      $cliente_id,
      $fecha_salida,
       $fecha_evento,
      $fecha_retorno,
      ($nota !== '' ? $nota : null),
      (int)current_user()['id']
    ]);

    $id = (int)$pdo->lastInsertId();
    flash_set('ok', "Reserva creada: {$codigo}. Agregá artículos.");
    header("Location: reserva_editar.php?id={$id}");
    exit;
  }
}

// --- LÓGICA DE LISTADO Y FILTROS ---
$q = trim((string)($_GET['q'] ?? ''));
$estado_filtro = (string)($_GET['estado'] ?? '');

$sql = "SELECT
          r.id,
          r.codigo,
          r.fecha_salida,
          r.fecha_evento,
          r.fecha_retorno,
          r.estado,
          r.creado_en,
          CONCAT(c.nombres,' ',c.apellidos) AS cliente,
          c.telefono,
          COALESCE(SUM(GREATEST(d.cantidad - d.entregado, 0)), 0) AS pend_entrega,
          COALESCE(SUM(GREATEST(d.entregado - (d.devuelto + d.danado + d.perdido), 0)), 0) AS pend_devol
        FROM reservas r
        INNER JOIN clientes c ON c.id = r.cliente_id
        LEFT JOIN reserva_detalle d ON d.reserva_id = r.id
        WHERE 1=1";

$params = [];
if ($q !== '') {
  $sql .= " AND (r.codigo LIKE ? OR c.nombres LIKE ? OR c.apellidos LIKE ? OR c.telefono LIKE ?) ";
  $params = array_merge($params, ["%$q%", "%$q%", "%$q%", "%$q%"]);
}
if (in_array($estado_filtro, ['BORRADOR','CONFIRMADA','ENTREGADA','DEVUELTA','CANCELADA'], true)) {
  $sql .= " AND r.estado = ? ";
  $params[] = $estado_filtro;
}

$sql .= " GROUP BY r.id ORDER BY r.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$clients = get_active_clients($pdo);
$ok  = flash_get('ok');
$err = flash_get('err');

require __DIR__ . '/../app/views/layout/header.php';
require __DIR__ . '/../app/views/layout/sidebar.php';
?>

<style>
  .page-topbar { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
  .card-soft { border-radius:14px; border: none; }
  .btn-pill { border-radius:10px; }
  .mono { font-family: ui-monospace, monospace; }
</style>

<div class="page-topbar mb-3">
  <div>
    <div class="text-muted small">Operación</div>
    <h3 class="mb-1">Reservas</h3>
    <div class="text-muted">Gestión de disponibilidad y flujo de inventario por fechas.</div>
  </div>
  <div>
    <a href="reservas.php" class="btn btn-outline-secondary btn-pill">Refrescar</a>
  </div>
</div>

<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($err || $error): ?><div class="alert alert-danger"><?= htmlspecialchars($err ?: $error) ?></div><?php endif; ?>

<div class="card card-soft shadow-sm mb-4">
  <div class="card-body p-4">
    <h5 class="mb-3">Crear reserva</h5>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div class="row g-3">
        <div class="col-12 col-lg-3">
          <label class="form-label">Cliente</label>
          <select name="cliente_id" class="form-select" required>
            <option value="">-- Seleccionar cliente activo --</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?= (int)$c['id'] ?>">
                <?= htmlspecialchars((string)$c['nombres'].' '.$c['apellidos']) ?> (<?= $c['telefono'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-lg-2">
  <label class="form-label">Fecha salida</label>
  <input type="date" name="fecha_salida" class="form-control" min="<?= $hoy ?>" required>
</div>


<div class="col-6 col-lg-2">
  <label class="form-label">Fecha retorno</label>
  <input type="date" name="fecha_retorno" class="form-control" min="<?= $hoy ?>" required>
</div>

        <div class="col-12">
          <button class="btn btn-primary btn-pill">Crear y agregar artículos</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card card-soft shadow-sm">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="mb-0">Listado General</h5>

      <form class="d-flex gap-2" method="get">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Buscar...">
        <select name="estado" class="form-select">
          <option value="">Todos los estados</option>
          <?php foreach (['BORRADOR','CONFIRMADA','ENTREGADA','DEVUELTA','CANCELADA'] as $e): ?>
            <option value="<?= $e ?>" <?= $estado_filtro===$e?'selected':'' ?>><?= $e ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary btn-pill">Filtrar</button>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr>
            <th>Código</th>
            <th>Cliente</th>
            <th>Fechas (Salida/Evento/Retorno)</th>
            <th>Alertas Operativas</th>
            <th>Estado</th>
            <th class="text-end">Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <?php 
              $pendE = (int)$r['pend_entrega'];
              $pendD = (int)$r['pend_devol'];
              $est = (string)$r['estado'];
              
              // Lógica de semáforos para fechas vencidas
              $isVencidaE = ($est === 'CONFIRMADA' && $pendE > 0 && $r['fecha_salida'] <= $hoy);
              $isVencidaD = ($est === 'ENTREGADA' && $pendD > 0 && $r['fecha_retorno'] <= $hoy);
            ?>
            <tr>
              <td class="mono fw-bold"><?= htmlspecialchars((string)$r['codigo']) ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string)$r['cliente']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars((string)$r['telefono']) ?></div>
              </td>
              <td>
  <div class="d-flex flex-column gap-1">
    <span class="badge text-bg-light border text-dark">
      Salida: <?= htmlspecialchars((string)$r['fecha_salida']) ?>
    </span>

    <span class="badge text-bg-light border text-dark">
      Evento: <?= htmlspecialchars((string)$r['fecha_evento']) ?>
    </span>

    <span class="badge text-bg-light border text-dark">
      Retorno: <?= htmlspecialchars((string)$r['fecha_retorno']) ?>
    </span>
  </div>
</td>
              <td>
                <?php if ($isVencidaE): ?><span class="badge text-bg-danger">ENTREGA ATRASADA</span><?php endif; ?>
                <?php if ($isVencidaD): ?><span class="badge text-bg-danger">RETORNO ATRASADO</span><?php endif; ?>
                <?php if (!$isVencidaE && !$isVencidaD): ?>
                    <span class="badge text-bg-<?= ($pendE+$pendD > 0 ? 'warning' : 'success') ?>">
                        <?= ($pendE+$pendD > 0 ? "Pendientes: ".($pendE+$pendD) : "Al día") ?>
                    </span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge text-bg-<?= $est==='CONFIRMADA'?'primary':($est==='ENTREGADA'?'info':($est==='DEVUELTA'?'success':'secondary')) ?>">
                    <?= $est ?>
                </span>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-primary btn-pill px-3" href="reserva_editar.php?id=<?= (int)$r['id'] ?>">Ver detalles</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>