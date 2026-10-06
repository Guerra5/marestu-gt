<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nota de entrega <?= h((string)$res['codigo']) ?></title>
<link rel="stylesheet" href="assets/css/pages/reserva_nota_entrega.css">
</head>
<body>
<div class="no-print">
  <button data-print>Imprimir</button>
  <a href="reserva_editar.php?id=<?= (int)$id ?>" data-style="reserva_nota_entrega-1">Volver</a>
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
<div class="title">Nota de entrega</div>
<!-- DATOS CLIENTE (COMO EN TU VERSIÓN ANTERIOR, SIMPLE) -->
<div class="muted" data-style="reserva_nota_entrega-2">
  <!-- <div>Reserva: <b><?= h((string)$res['codigo']) ?></b></div> -->
  <div>Cliente: <b><?= h((string)$res['cliente']) ?></b></div>
  <div>Teléfono: <?= h((string)$res['telefono']) ?></div>
  <div>Correo: <?= h((string)($res['email'] ?? '')) ?></div>
  <div>Dirección: <?= h((string)($res['direccion'] ?? '')) ?></div>
  <?php if (!empty($res['direccion_evento'])): ?>
  <div>
    Dirección del evento:
    <b><?= h((string)$res['direccion_evento']) ?></b>
  </div>
<?php endif; ?>
</div>
<!-- TABLA (COMO ANTES) -->
<table>
  <thead>
    <tr>
      <th data-style="reserva_nota_entrega-3" class="center">Cantidad</th>
      <th>Descripción</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td class="center"><?= (int)$it['cantidad'] ?></td>
        <td><?= h((string)$it['categoria']) ?> · <?= h((string)$it['nombre']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php foreach ($extras as $ex): ?>
      <tr>
        <td class="center"><?= (int)$ex['cantidad'] ?></td>
        <td>
          <?= h((string)$ex['descripcion']) ?>
          <?php if (!empty($ex['proveedor'])): ?>
            <span class="muted">(<?= h((string)$ex['proveedor']) ?>)</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items && !$extras): ?>
      <tr><td colspan="2" class="center muted">Sin ítems.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
<!-- NOTA / INDICACIONES DEL CLIENTE -->
<?php if ($nota_reserva !== ''): ?>
  <div class="nota-reserva">
    <div class="titulo-nota">Notas / Indicaciones del cliente</div>
    <div class="contenido-nota"><?= h($nota_reserva) ?></div>
  </div>
<?php endif; ?>
<!-- FIRMA SIMPLE (COMO TU EJEMPLO) -->
<div class="sign">
  <div><b>Recibido / Aceptado por el cliente</b></div>
  <div class="line"></div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
