<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

start_app_session();
require_login();

$pdo = db();

/**
 * Genera el siguiente código correlativo de reserva.
 */
function next_res_code(PDO $pdo): string {
  $st = $pdo->query("SELECT id FROM reservas ORDER BY id DESC LIMIT 1");
  $row = $st->fetch();

  $next = $row ? ((int)$row['id'] + 1) : 1;

  return 'RES-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

/**
 * Obtiene los clientes activos.
 */
function get_active_clients(PDO $pdo): array {
  $st = $pdo->query("
    SELECT id, nombres, apellidos, telefono
    FROM clientes
    WHERE estado = 'ACTIVO'
    ORDER BY nombres ASC, apellidos ASC
  ");

  return $st->fetchAll();
}

/**
 * Valida que una cadena corresponda exactamente a una fecha YYYY-MM-DD.
 */
function valid_date(string $date): bool {
  if ($date === '') {
    return false;
  }

  $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
  $errors = DateTimeImmutable::getLastErrors();

  if ($parsed === false) {
    return false;
  }

  if (
    is_array($errors) &&
    ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
  ) {
    return false;
  }

  return $parsed->format('Y-m-d') === $date;
}

$error = null;
$hoy = date('Y-m-d');

/*
 * Valores para conservar el formulario cuando exista un error.
 */
$form_cliente_id   = (int)($_POST['cliente_id'] ?? 0);
$form_fecha_salida = trim((string)($_POST['fecha_salida'] ?? ''));
$form_fecha_evento = trim((string)($_POST['fecha_evento'] ?? ''));
$form_fecha_retorno = trim((string)($_POST['fecha_retorno'] ?? ''));
$form_nota = trim((string)($_POST['nota'] ?? ''));

// =========================================================
// ACCIÓN: CREAR RESERVA
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? null)) {
    flash_set('err', 'Token inválido. Recargá la página.');
    header('Location: reservas.php');
    exit;
  }

  $cliente_id   = $form_cliente_id;
  $fecha_salida = $form_fecha_salida;
  $fecha_evento = $form_fecha_evento;
  $fecha_retorno = $form_fecha_retorno;
  $nota = $form_nota;

  // Validaciones obligatorias
  if ($cliente_id <= 0) {
    $error = 'Seleccioná un cliente.';
  } elseif (
    $fecha_salida === '' ||
    $fecha_evento === '' ||
    $fecha_retorno === ''
  ) {
    $error = 'Las fechas de salida, evento y retorno son obligatorias.';
  } elseif (
    !valid_date($fecha_salida) ||
    !valid_date($fecha_evento) ||
    !valid_date($fecha_retorno)
  ) {
    $error = 'Una o más fechas no tienen un formato válido.';
  } elseif ($fecha_salida < $hoy) {
    $error = "No podés crear una reserva con fecha de salida anterior a hoy ({$hoy}).";
  } elseif ($fecha_evento < $fecha_salida) {
    $error = 'La fecha del evento no puede ser anterior a la fecha de salida.';
  } elseif ($fecha_retorno < $fecha_evento) {
    $error = 'La fecha de retorno no puede ser anterior a la fecha del evento.';
  } else {
    /*
     * Verificamos que el cliente enviado exista y esté activo.
     */
    $stCliente = $pdo->prepare("
      SELECT id
      FROM clientes
      WHERE id = ?
        AND estado = 'ACTIVO'
      LIMIT 1
    ");
    $stCliente->execute([$cliente_id]);

    if (!$stCliente->fetch()) {
      $error = 'El cliente seleccionado no existe o está inactivo.';
    } else {
      try {
        $pdo->beginTransaction();

        $codigo = next_res_code($pdo);

        $ins = $pdo->prepare("
          INSERT INTO reservas (
            codigo,
            cliente_id,
            fecha_salida,
            fecha_evento,
            fecha_retorno,
            estado,
            nota,
            creado_por
          )
          VALUES (?, ?, ?, ?, ?, 'BORRADOR', ?, ?)
        ");

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

        $pdo->commit();

        flash_set(
          'ok',
          "Reserva creada: {$codigo}. Agregá los artículos correspondientes."
        );

        header("Location: reserva_editar.php?id={$id}");
        exit;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }

        $error = 'No fue posible crear la reserva: ' . $e->getMessage();
      }
    }
  }
}

// =========================================================
// LISTADO Y FILTROS
// =========================================================
$q = trim((string)($_GET['q'] ?? ''));
$estado_filtro = (string)($_GET['estado'] ?? '');

$sql = "
  SELECT
    r.id,
    r.codigo,
    r.fecha_salida,
    r.fecha_evento,
    r.fecha_retorno,
    r.estado,
    r.creado_en,
    CONCAT(c.nombres, ' ', c.apellidos) AS cliente,
    c.telefono,
    COALESCE(
      SUM(GREATEST(d.cantidad - d.entregado, 0)),
      0
    ) AS pend_entrega,
    COALESCE(
      SUM(
        GREATEST(
          d.entregado - (d.devuelto + d.danado + d.perdido),
          0
        )
      ),
      0
    ) AS pend_devol
  FROM reservas r
  INNER JOIN clientes c
    ON c.id = r.cliente_id
  LEFT JOIN reserva_detalle d
    ON d.reserva_id = r.id
  WHERE 1 = 1
";

$params = [];

if ($q !== '') {
  $sql .= "
    AND (
      r.codigo LIKE ?
      OR c.nombres LIKE ?
      OR c.apellidos LIKE ?
      OR c.telefono LIKE ?
    )
  ";

  $search = "%{$q}%";

  $params[] = $search;
  $params[] = $search;
  $params[] = $search;
  $params[] = $search;
}

$estados_validos = [
  'BORRADOR',
  'CONFIRMADA',
  'ENTREGADA',
  'DEVUELTA',
  'CANCELADA'
];

if (in_array($estado_filtro, $estados_validos, true)) {
  $sql .= " AND r.estado = ? ";
  $params[] = $estado_filtro;
}

$sql .= "
  GROUP BY
    r.id,
    r.codigo,
    r.fecha_salida,
    r.fecha_evento,
    r.fecha_retorno,
    r.estado,
    r.creado_en,
    c.nombres,
    c.apellidos,
    c.telefono
  ORDER BY r.id DESC
";

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
  .page-topbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
  }

  .card-soft {
    border-radius: 14px;
    border: none;
  }

  .btn-pill {
    border-radius: 10px;
  }

  .mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco,
      Consolas, "Liberation Mono", "Courier New", monospace;
  }

  .reservation-dates {
    min-width: 175px;
  }

  @media (max-width: 767.98px) {
    .page-topbar {
      flex-direction: column;
      align-items: stretch;
    }

    .page-topbar .btn {
      width: 100%;
    }

    .filter-form {
      flex-direction: column;
      width: 100%;
    }

    .filter-form .form-control,
    .filter-form .form-select,
    .filter-form .btn {
      width: 100%;
    }
  }
</style>

<div class="page-topbar mb-3">
  <div>
    <div class="text-muted small">Operación</div>
    <h3 class="mb-1">Reservas</h3>
    <div class="text-muted">
      Gestión de disponibilidad y flujo de inventario por fechas.
    </div>
  </div>

  <div>
    <a
      href="reservas.php"
      class="btn btn-outline-secondary btn-pill"
    >
      Refrescar
    </a>
  </div>
</div>

<?php if ($ok): ?>
  <div class="alert alert-success">
    <?= htmlspecialchars($ok) ?>
  </div>
<?php endif; ?>

<?php if ($err || $error): ?>
  <div class="alert alert-danger">
    <?= htmlspecialchars($err ?: $error) ?>
  </div>
<?php endif; ?>

<!-- =====================================================
     CREAR RESERVA
====================================================== -->
<div class="card card-soft shadow-sm mb-4">
  <div class="card-body p-4">
    <h5 class="mb-3">Crear reserva</h5>

    <form method="post" autocomplete="off" id="formCrearReserva">
      <input
        type="hidden"
        name="csrf"
        value="<?= htmlspecialchars(csrf_token()) ?>"
      >

      <div class="row g-3">
        <div class="col-12 col-lg-3">
          <label class="form-label">Cliente</label>

          <select
            name="cliente_id"
            class="form-select"
            required
          >
            <option value="">
              -- Seleccionar cliente activo --
            </option>

            <?php foreach ($clients as $c): ?>
              <option
                value="<?= (int)$c['id'] ?>"
                <?= $form_cliente_id === (int)$c['id'] ? 'selected' : '' ?>
              >
                <?= htmlspecialchars(
                  (string)$c['nombres'] . ' ' . (string)$c['apellidos']
                ) ?>
                (<?= htmlspecialchars((string)$c['telefono']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label">Fecha salida</label>

          <input
            type="date"
            name="fecha_salida"
            id="fechaSalida"
            class="form-control"
            min="<?= htmlspecialchars($hoy) ?>"
            value="<?= htmlspecialchars($form_fecha_salida) ?>"
            required
          >
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label">Fecha evento</label>

          <input
            type="date"
            name="fecha_evento"
            id="fechaEvento"
            class="form-control"
            min="<?= htmlspecialchars($hoy) ?>"
            value="<?= htmlspecialchars($form_fecha_evento) ?>"
            required
          >
        </div>

        <div class="col-12 col-md-4 col-lg-2">
          <label class="form-label">Fecha retorno</label>

          <input
            type="date"
            name="fecha_retorno"
            id="fechaRetorno"
            class="form-control"
            min="<?= htmlspecialchars($hoy) ?>"
            value="<?= htmlspecialchars($form_fecha_retorno) ?>"
            required
          >
        </div>

        <div class="col-12 col-lg-3">
          <label class="form-label">Nota</label>

          <input
            type="text"
            name="nota"
            class="form-control"
            value="<?= htmlspecialchars($form_nota) ?>"
            placeholder="Ej: Evento en salón municipal"
          >
        </div>

        <div class="col-12">
          <div class="small text-muted mb-2">
            La fecha de salida debe ser igual o anterior a la fecha del
            evento, y la fecha de retorno debe ser igual o posterior.
          </div>

          <button
            type="submit"
            class="btn btn-primary btn-pill"
          >
            Crear y agregar artículos
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- =====================================================
     LISTADO GENERAL
====================================================== -->
<div class="card card-soft shadow-sm">
  <div class="card-body p-4">
    <div
      class="d-flex flex-column flex-lg-row
             align-items-lg-center justify-content-between
             gap-3 mb-3"
    >
      <h5 class="mb-0">Listado general</h5>

      <form
        class="d-flex gap-2 filter-form"
        method="get"
      >
        <input
          type="text"
          name="q"
          value="<?= htmlspecialchars($q) ?>"
          class="form-control"
          placeholder="Buscar..."
        >

        <select name="estado" class="form-select">
          <option value="">Todos los estados</option>

          <?php foreach ($estados_validos as $estado): ?>
            <option
              value="<?= htmlspecialchars($estado) ?>"
              <?= $estado_filtro === $estado ? 'selected' : '' ?>
            >
              <?= htmlspecialchars($estado) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button class="btn btn-outline-primary btn-pill">
          Filtrar
        </button>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr>
            <th>Código</th>
            <th>Cliente</th>
            <th>Fechas</th>
            <th>Alertas operativas</th>
            <th>Estado</th>
            <th class="text-end">Acción</th>
          </tr>
        </thead>

        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td
                colspan="6"
                class="text-center text-muted py-4"
              >
                No hay reservas registradas.
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($rows as $r): ?>
            <?php
              $pendE = (int)$r['pend_entrega'];
              $pendD = (int)$r['pend_devol'];
              $est = (string)$r['estado'];

              $isVencidaE = (
                $est === 'CONFIRMADA' &&
                $pendE > 0 &&
                (string)$r['fecha_salida'] <= $hoy
              );

              $isVencidaD = (
                $est === 'ENTREGADA' &&
                $pendD > 0 &&
                (string)$r['fecha_retorno'] <= $hoy
              );

              $estadoBadge = match ($est) {
                'CONFIRMADA' => 'primary',
                'ENTREGADA'  => 'info',
                'DEVUELTA'   => 'success',
                'CANCELADA'  => 'danger',
                default      => 'secondary',
              };
            ?>

            <tr>
              <td class="mono fw-bold">
                <?= htmlspecialchars((string)$r['codigo']) ?>
              </td>

              <td>
                <div class="fw-semibold">
                  <?= htmlspecialchars((string)$r['cliente']) ?>
                </div>

                <div class="text-muted small">
                  <?= htmlspecialchars((string)$r['telefono']) ?>
                </div>
              </td>

              <td>
                <div class="d-flex flex-column gap-1 reservation-dates">
                  <span class="badge text-bg-light border text-dark text-start">
                    Salida:
                    <?= htmlspecialchars((string)$r['fecha_salida']) ?>
                  </span>

                  <span class="badge text-bg-light border text-dark text-start">
                    Evento:
                    <?= htmlspecialchars((string)($r['fecha_evento'] ?? '')) ?>
                  </span>

                  <span class="badge text-bg-light border text-dark text-start">
                    Retorno:
                    <?= htmlspecialchars((string)$r['fecha_retorno']) ?>
                  </span>
                </div>
              </td>

              <td>
                <div class="d-flex flex-column align-items-start gap-1">
                  <?php if ($isVencidaE): ?>
                    <span class="badge text-bg-danger">
                      ENTREGA ATRASADA
                    </span>
                  <?php endif; ?>

                  <?php if ($isVencidaD): ?>
                    <span class="badge text-bg-danger">
                      RETORNO ATRASADO
                    </span>
                  <?php endif; ?>

                  <?php if (!$isVencidaE && !$isVencidaD): ?>
                    <span
                      class="badge text-bg-<?=
                        ($pendE + $pendD > 0) ? 'warning' : 'success'
                      ?>"
                    >
                      <?=
                        ($pendE + $pendD > 0)
                          ? 'Pendientes: ' . ($pendE + $pendD)
                          : 'Al día'
                      ?>
                    </span>
                  <?php endif; ?>
                </div>
              </td>

              <td>
                <span class="badge text-bg-<?= $estadoBadge ?>">
                  <?= htmlspecialchars($est) ?>
                </span>
              </td>

              <td class="text-end">
                <a
                  class="btn btn-sm btn-primary btn-pill px-3"
                  href="reserva_editar.php?id=<?= (int)$r['id'] ?>"
                >
                  Ver detalles
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  const fechaSalida = document.getElementById('fechaSalida');
  const fechaEvento = document.getElementById('fechaEvento');
  const fechaRetorno = document.getElementById('fechaRetorno');
  const form = document.getElementById('formCrearReserva');

  if (!fechaSalida || !fechaEvento || !fechaRetorno || !form) {
    return;
  }

  function actualizarLimites() {
    const salida = fechaSalida.value;
    const evento = fechaEvento.value;

    fechaEvento.min = salida || '<?= htmlspecialchars($hoy) ?>';

    if (salida && fechaEvento.value && fechaEvento.value < salida) {
      fechaEvento.value = salida;
    }

    const minimoRetorno = fechaEvento.value || salida || '<?= htmlspecialchars($hoy) ?>';
    fechaRetorno.min = minimoRetorno;

    if (
      minimoRetorno &&
      fechaRetorno.value &&
      fechaRetorno.value < minimoRetorno
    ) {
      fechaRetorno.value = minimoRetorno;
    }

    if (evento && fechaRetorno.value && fechaRetorno.value < evento) {
      fechaRetorno.value = evento;
    }
  }

  fechaSalida.addEventListener('change', actualizarLimites);
  fechaEvento.addEventListener('change', actualizarLimites);
  fechaRetorno.addEventListener('change', actualizarLimites);

  form.addEventListener('submit', function (event) {
    if (
      fechaSalida.value === '' ||
      fechaEvento.value === '' ||
      fechaRetorno.value === ''
    ) {
      event.preventDefault();
      alert('Las tres fechas son obligatorias.');
      return;
    }

    if (fechaEvento.value < fechaSalida.value) {
      event.preventDefault();
      alert('La fecha del evento no puede ser anterior a la fecha de salida.');
      fechaEvento.focus();
      return;
    }

    if (fechaRetorno.value < fechaEvento.value) {
      event.preventDefault();
      alert('La fecha de retorno no puede ser anterior a la fecha del evento.');
      fechaRetorno.focus();
    }
  });

  actualizarLimites();
})();
</script>

<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>