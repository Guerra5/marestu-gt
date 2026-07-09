<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';

start_app_session();
require_login();
$pdo = db();

function get_reserva(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare("SELECT r.*,
                              CONCAT(c.nombres,' ',c.apellidos) AS cliente,
                              c.telefono,
                              c.email,
                              c.direccion
                       FROM reservas r
                       INNER JOIN clientes c ON c.id = r.cliente_id
                       WHERE r.id=? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch();
  return $row ?: null;
}

function reserva_items(PDO $pdo, int $reserva_id): array {
  $st = $pdo->prepare("SELECT d.id, d.cantidad,
                              a.codigo, a.nombre
                       FROM reserva_detalle d
                       INNER JOIN articulos a ON a.id=d.articulo_id
                       WHERE d.reserva_id=?
                       ORDER BY a.nombre ASC");
  $st->execute([$reserva_id]);
  return $st->fetchAll();
}

function reserva_extras(PDO $pdo, int $reserva_id): array {
  $st = $pdo->prepare("SELECT id, descripcion, proveedor, cantidad
                       FROM reserva_extras
                       WHERE reserva_id=?
                       ORDER BY id ASC");
  $st->execute([$reserva_id]);
  return $st->fetchAll();
}

function date_add_days(string $ymd, int $days): string {
  $dt = new DateTime($ymd);
  $dt->modify(($days >= 0 ? '+' : '') . $days . ' day');
  return $dt->format('Y-m-d');
}

function h(?string $v): string {
  return htmlspecialchars((string)($v ?? ''));
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo "ID inválido."; exit; }

$res = get_reserva($pdo, $id);
if (!$res) { echo "Reserva no encontrada."; exit; }

$items  = reserva_items($pdo, $id);
$extras = reserva_extras($pdo, $id);

// ===== Datos empresa (hardcode por ahora) =====
$empresa_email = 'cori2leon@gmail.com';
$empresa_tel   = 'Tel. 58599321 - 54123635';

// ===== Fechas =====
$fecha_entrega = (string)$res['fecha_salida'];
$fecha_evento  = date_add_days($fecha_entrega, 1);
$fecha_desmont = (string)$res['fecha_retorno'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nota de entrega <?= h((string)$res['codigo']) ?></title>

<style>
  body{font-family:Arial, Helvetica, sans-serif; color:#111; margin:24px;}
  .no-print{margin-bottom:10px;}
  @media print {.no-print{display:none;} body{margin:0;}}

  /* ===== HEADER NUEVO (EL QUE TE GUSTÓ) ===== */
  .hdr{
    display:grid;
    grid-template-columns: 1fr 1fr;
    align-items:start;
    gap: 10px;
    margin-bottom: 8px;
  }
  .brand{display:flex; flex-direction:column; gap:6px;}
  .logo{width:220px; height:auto; display:block;}
  .brand a{color:#0b53d0; text-decoration:underline; font-size:14px;}
  .brand .tel{color:#0a7c1f; font-weight:700; font-size:16px;}

  .dates{justify-self:end; font-size:13px; line-height:1.4;}
  .dates b{display:inline-block; width:140px;}

  .title{
    text-align:center;
    font-size:28px;
    font-weight:900;
    color:#d60000;
    margin: 14px 0 12px;
  }

  /* ===== CUERPO COMO ANTES (TABLA + FIRMA SIMPLE) ===== */
  .muted{color:#555; font-size:12px;}

  table{width:100%; border-collapse:collapse; margin-top:12px;}
  th,td{border:1px solid #ddd; padding:8px; font-size:13px; vertical-align:top;}
  th{background:#f4f4f4;}
  .center{text-align:center;}
  .mt{margin-top:14px;}
  .sign{
    margin-top: 18px;
    font-size: 14px;
  }
  .sign .line{
    margin-top: 18px;
    width: 280px;
    border-bottom: 1px solid #555;
    height: 1px;
  }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">Imprimir</button>
  <a href="reserva_editar.php?id=<?= (int)$id ?>" style="margin-left:10px;">Volver</a>
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
<div class="muted" style="font-size:14px; color:#111;">
  <!-- <div>Reserva: <b><?= h((string)$res['codigo']) ?></b></div> -->
  <div>Cliente: <b><?= h((string)$res['cliente']) ?></b></div>
  <div>Teléfono: <?= h((string)$res['telefono']) ?></div>
  <div>Correo: <?= h((string)($res['email'] ?? '')) ?></div>
  <div>Dirección: <?= h((string)($res['direccion'] ?? '')) ?></div>
</div>

<!-- TABLA (COMO ANTES) -->
<table>
  <thead>
    <tr>
      <th style="width:140px;" class="center">Cantidad</th>
      <th>Descripción</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td class="center"><?= (int)$it['cantidad'] ?></td>
        <td><?= h((string)$it['nombre']) ?> </td>
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

<!-- FIRMA SIMPLE (COMO TU EJEMPLO) -->
<div class="sign">
  <div><b>Recibido / Aceptado por el cliente</b></div>
  <div class="line"></div>
</div>

</body>
</html>

