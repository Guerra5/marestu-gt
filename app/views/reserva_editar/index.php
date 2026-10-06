<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<link rel="stylesheet" href="assets/css/pages/reserva_editar.css">
<div class="d-flex align-items-start justify-content-between mb-3 reservation-heading">
  <div>
    <div class="text-muted small">
      Reserva
    </div>
    <h3 class="mb-1">
      <span class="mono">
        <?= htmlspecialchars((string)$res['codigo']) ?>
      </span>
    </h3>
    <div class="text-muted">
      <b>
        <?= htmlspecialchars((string)$res['cliente']) ?>
      </b>
      ·
      <?= htmlspecialchars((string)$res['telefono']) ?>
      <br>
      <span class="badge text-bg-dark">
        Salida:
        <?= htmlspecialchars((string)$res['fecha_salida']) ?>
      </span>
      <?php if (!empty($res['fecha_evento'])): ?>
        <span class="badge text-bg-info">
          Evento:
          <?= htmlspecialchars((string)$res['fecha_evento']) ?>
        </span>
      <?php endif; ?>
      <span class="badge text-bg-secondary">
        Retorno:
        <?= htmlspecialchars((string)$res['fecha_retorno']) ?>
      </span>
      <span class="badge text-bg-primary">
        Estado:
        <?= htmlspecialchars((string)$res['estado']) ?>
      </span>
    </div>
  </div>
  <div class="d-flex gap-2 reservation-heading-actions">
    <a
      href="reservas.php"
      class="btn btn-outline-secondary btn-pill"
    >
      Volver
    </a>
  </div>
</div>
<?php if ($ok): ?>
  <div class="alert alert-success">
    <?= htmlspecialchars($ok) ?>
  </div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger">
    <?= htmlspecialchars($err) ?>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>
<div class="card card-soft mb-3"><div class="card-body">
  <h5>Dirección del evento</h5>
  <p><?= nl2br(htmlspecialchars((string)($res['direccion_evento'] ?? 'Sin dirección adicional registrada.'))) ?></p>
  <?php if (can('reservas.gestionar') && in_array($res['estado'], ['BORRADOR', 'CONFIRMADA'], true)): ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="action" value="direccion_evento">
    <label class="form-label" for="direccionEvento">Lugar de entrega o del evento</label>
    <textarea class="form-control mb-2" id="direccionEvento" name="direccion_evento" rows="2" maxlength="2000"><?= htmlspecialchars((string)($res['direccion_evento'] ?? '')) ?></textarea>
    <div class="form-text mb-2">Opcional. No modifica la dirección del cliente.</div>
    <button class="btn btn-primary" type="submit">Guardar dirección</button>
  </form>
  <?php endif; ?>
</div></div>
<div class="row g-3">
  <!-- =====================================================
       DETALLE
  ====================================================== -->
  <?php require __DIR__ . '/_pedido.php'; ?>
  <!-- =====================================================
       ACCIONES / TOTALES
  ====================================================== -->
  <?php require __DIR__ . '/_resumen.php'; ?>
</div>
<script src="assets/js/pages/reserva_editar.js"></script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
