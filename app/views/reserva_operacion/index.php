<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<link rel="stylesheet" href="assets/css/pages/reserva_operacion.css">
<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <div class="text-muted small">
      Operación diaria
    </div>
    <h3 class="mb-1">
      Reserva
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
      <div class="mb-2"><strong>Dirección del evento:</strong>
        <?= nl2br(htmlspecialchars((string)($res['direccion_evento'] ?? $res['direccion_cliente'] ?? 'Sin dirección registrada.'))) ?>
      </div>
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
  <div class="d-flex gap-2">
    <a
      href="reserva_editar.php?id=<?= $id ?>"
      class="btn btn-outline-secondary btn-pill"
    >
      Volver a detalle
    </a>
    <a
      href="reservas.php"
      class="btn btn-outline-secondary btn-pill"
    >
      Reservas
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
<div class="row g-3">
  <?php require __DIR__ . '/_detalle.php'; ?>
  <?php require __DIR__ . '/_operacion.php'; ?>
</div>
<!-- =====================================================
     MODAL EDITAR CANTIDAD DEL PEDIDO
====================================================== -->
<?php if (is_admin() && in_array($estado, ['CONFIRMADA', 'ENTREGADA'], true)): ?>
<?php require __DIR__ . '/_modal_editar_pedido.php'; ?>
<?php endif; ?>
<!-- =====================================================
     MODAL AGREGAR AL PEDIDO
====================================================== -->
<?php if (
  is_admin() &&
  in_array(
    $estado,
    ['CONFIRMADA', 'ENTREGADA'],
    true
  )
): ?>
<?php require __DIR__ . '/_modal_agregar_pedido.php'; ?>
<script src="assets/js/pages/reserva_operacion.js"></script>
<?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
