<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<link rel="stylesheet" href="assets/css/pages/reservas.css">
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
<?php if (can('reservas.gestionar')): ?>
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
          <label class="form-label" for="direccionEvento">Dirección del evento (opcional)</label>
          <textarea class="form-control" id="direccionEvento" name="direccion_evento" rows="2" maxlength="2000"><?= htmlspecialchars($form_direccion_evento) ?></textarea>
          <div class="form-text">Si es distinta, indicá aquí el lugar de entrega o del evento. No modifica la dirección del cliente.</div>
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
<?php endif; ?>
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
<script type="application/json" id="reservas-data"><?= json_encode(['value0' => $hoy, 'value1' => $hoy], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="assets/js/pages/reservas.js"></script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
