<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cotización <?= h((string)$res['codigo']) ?></title>
<link rel="stylesheet" href="assets/css/pages/reserva_cotizacion.css">
</head>
<body>
<div class="no-print">
  <button data-print>Imprimir</button>
  <a href="reserva_editar.php?id=<?= (int)$id ?>" data-style="reserva_cotizacion-1">Volver</a>
</div>
<!-- HEADER NUEVO -->
<div class="hdr">
  <div class="brand">
    <img class="logo" src="assets/logo_marestu.png" alt="MARESTU">
    <a href="mailto:<?= h($empresa_email) ?>"><?= h($empresa_email) ?></a>
    <div class="tel"><?= h($empresa_tel) ?></div>
  </div>
  <div class="dates">
    <div><b>Fecha entrega</b> <?= h($fecha_entrega) ?></div>
    <div><b>Fecha evento</b> <?= h($fecha_evento) ?></div>
    <div><b>Fecha desmontaje</b> <?= h($fecha_desmont) ?></div>
  </div>
</div>
<div class="title">Cotización</div>
<!-- Datos cliente (simple, como nota) -->
<div data-style="reserva_cotizacion-2">
  <!-- <div>Reserva: <b><?= h((string)$res['codigo']) ?></b></div> -->
  <div>Cliente: <b><?= h((string)$res['cliente']) ?></b></div>
  <div>Teléfono: <?= h((string)$res['telefono']) ?></div>
  <div>Correo: <?= h((string)($res['email'] ?? '')) ?></div>
  <div>Dirección del cliente: <?= h((string)($res['direccion'] ?? '')) ?></div>
  <?php if (!empty($res['direccion_evento'])): ?>
  <div>Dirección del evento: <b><?= h((string)$res['direccion_evento']) ?></b></div>
  <?php endif; ?>
</div>
<!-- TABLA (como lo tenías) -->
<table>
  <thead>
    <tr>
      <th data-style="reserva_cotizacion-3" class="center">Cant.</th>
      <th>Descripción</th>
      <th data-style="reserva_cotizacion-4" class="right">Precio unidad</th>
      <th data-style="reserva_cotizacion-4" class="right">Total</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $it): ?>
      <?php
        $qty = (int)$it['cantidad'];
        $pu  = (float)$it['precio_unitario'];
        $sub = $qty * $pu;
        $total += $sub;
      ?>
      <tr>
        <td class="center"><?= $qty ?></td>
        <td><?= h((string)$it['nombre']) ?> </td>
        <td class="right"><?= money($pu) ?></td>
        <td class="right"><?= money($sub) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php foreach ($extras as $ex): ?>
      <?php
        $qty = (int)$ex['cantidad'];
        $pu  = (float)$ex['precio_unitario'];
        $sub = (float)$ex['subtotal'];
        $total += $sub;
      ?>
      <tr>
        <td class="center"><?= $qty ?></td>
        <td>
          <?= h((string)$ex['descripcion']) ?>
          <?php if (!empty($ex['proveedor'])): ?>
            <span class="muted">(<?= h((string)$ex['proveedor']) ?>)</span>
          <?php endif; ?>
        </td>
        <td class="right"><?= money($pu) ?></td>
        <td class="right"><?= money($sub) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items && !$extras): ?>
      <tr><td colspan="4" class="center muted">Sin ítems.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
<!-- TOTAL (como lo tenías: alineado a la derecha) -->
<div class="total-row">
  <div class="label">Total</div>
  <div class="value"><?= money($total) ?></div>
</div>
<!-- NOTAS (como lo tenías) -->
<div class="notes">
  <h4>Notas</h4>
  <ul>
    <li>La cotización no bloquea stock hasta confirmar la reserva.</li>
    <li>Daños/pérdidas se cobran según reposición/costo.</li>
    <li>Las fechas corresponden a la información registrada en la reserva.</li>
  </ul>
</div>
<!-- FIRMA -->
<div data-style="reserva_cotizacion-5">
  <div data-style="reserva_cotizacion-6"></div>
  <div data-style="reserva_cotizacion-7">
    Firma del cliente
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
