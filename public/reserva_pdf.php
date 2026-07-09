<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

start_app_session();
require_login();

// SEGURIDAD: Solo el administrador puede acceder a la generación de PDFs
if (current_user()['rol'] !== 'ADMIN') {
    die("Acceso denegado. Solo administradores pueden generar documentos.");
}

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$type = (string)($_GET['type'] ?? 'cotizacion');

// Consulta de cabecera: Reserva y Cliente
$st = $pdo->prepare("SELECT r.*, c.nombres, c.apellidos, c.telefono, c.direccion 
                       FROM reservas r 
                       INNER JOIN clientes c ON c.id = r.cliente_id 
                       WHERE r.id = ?");
$st->execute([$id]);
$res = $st->fetch();

if (!$res) exit("Reserva no encontrada.");

// Consulta de artículos con precio de reposición
$items = $pdo->prepare("SELECT d.*, a.nombre, a.codigo, a.precio_reposicion 
                          FROM reserva_detalle d 
                          INNER JOIN articulos a ON a.id = d.articulo_id 
                          WHERE d.reserva_id = ?");
$items->execute([$id]);
$rows = $items->fetchAll();

// Consulta de extras
$extras = $pdo->prepare("SELECT * FROM reserva_extras WHERE reserva_id = ?");
$extras->execute([$id]);
$ext_rows = $extras->fetchAll();

$html = '
<html>
<head>
<style>
    body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 15px; }
    .header { text-align: center; margin-bottom: 10px; }
    .header h1 { margin: 0; font-size: 24px; }
    .info-table { width: 100%; border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th, .data-table td { border: 1px solid #000; padding: 6px; text-align: left; }
    .data-table th { background-color: #f2f2f2; }
    .footer { margin-top: 20px; font-size: 10px; line-height: 1.4; }
    .total-box { margin-top: 15px; text-align: right; font-weight: bold; font-size: 13px; }
</style>
</head>
<body>
    <div class="header">
        <h1>Marestu</h1>
        <div style="font-weight: bold; font-size: 14px;">EVENTOS</div>
        <div>cori2leon@gmail.com | Tel. 58599321 - 54123635</div>
    </div>

    <div style="text-align: center; font-weight: bold; font-size: 16px; margin-bottom: 15px; text-decoration: underline;">
        ' . ($type === 'nota' ? 'NOTA DE ENVÍO' : 'COTIZACIÓN') . '
    </div>

    <table class="info-table">
        <tr>
            <td><strong>CLIENTE:</strong> ' . htmlspecialchars($res['nombres'] . ' ' . $res['apellidos']) . '</td>
            <td><strong>FECHA ENTREGA:</strong> ' . $res['fecha_salida'] . '</td>
        </tr>
        <tr>
            <td><strong>DIRECCIÓN:</strong> ' . htmlspecialchars($res['direccion'] ?? 'N/A') . '</td>
            <td><strong>FECHA EVENTO:</strong> ' . $res['fecha_salida'] . '</td>
        </tr>
        <tr>
            <td><strong>TELÉFONO:</strong> ' . htmlspecialchars($res['telefono']) . '</td>
            <td><strong>FECHA DESMONTAJE:</strong> ' . $res['fecha_retorno'] . '</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="10%">CANT.</th>
                <th>DESCRIPCIÓN</th>
                ' . ($type === 'cotizacion' ? '<th width="15%">P. UNIDAD</th><th width="15%">TOTAL</th><th width="15%">REPOSICIÓN</th>' : '') . '
            </tr>
        </thead>
        <tbody>';

$total_gral = 0;
foreach ($rows as $it) {
    $sub = (float)$it['precio_unitario'] * (int)$it['cantidad'];
    $total_gral += $sub;
    $html .= '<tr>
                <td>' . $it['cantidad'] . '</td>
                <td>' . htmlspecialchars($it['nombre']) . '</td>
                ' . ($type === 'cotizacion' ? '
                <td>Q ' . number_format((float)$it['precio_unitario'], 2) . '</td>
                <td>Q ' . number_format($sub, 2) . '</td>
                <td>' . ($it['precio_reposicion'] > 0 ? 'Q '.number_format((float)$it['precio_reposicion'], 2) : 'Reparación') . '</td>' : '') . '
              </tr>';
}

foreach ($ext_rows as $ex) {
    $sub = (float)$ex['precio_unitario'] * (int)$ex['cantidad'];
    $total_gral += $sub;
    $html .= '<tr>
                <td>' . $ex['cantidad'] . '</td>
                <td>' . htmlspecialchars($ex['descripcion']) . ' (Extra)</td>
                ' . ($type === 'cotizacion' ? '
                <td>Q ' . number_format((float)$ex['precio_unitario'], 2) . '</td>
                <td>Q ' . number_format($sub, 2) . '</td>
                <td>-</td>' : '') . '
              </tr>';
}

$html .= '</tbody></table>';

if ($type === 'cotizacion') {
    $html .= '<div class="total-box">TOTAL A PAGAR: Q ' . number_format($total_gral, 2) . '</div>';
}

$html .= '
    <div class="footer">
        <strong>Información importante:</strong><br>
        • Entregamos un día antes, recogemos un día después. No trabajamos domingo. El total del alquiler no incluye montaje.<br>
        • Los daños ocasionados al mobiliario se le cobran al cliente. Pago anticipado. No hacemos devoluciones de anticipo.
    </div>

    <table style="width: 100%; margin-top: 50px; text-align: center;">
        <tr>
            <td style="border-top: 1px solid #000; width: 40%;">Ingrid de León - Asesora</td>
            <td style="width: 20%;"></td>
            <td style="border-top: 1px solid #000; width: 40%;">Aceptado por el cliente</td>
        </tr>
    </table>
</body>
</html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream($res['codigo'] . "_" . $type . ".pdf", ["Attachment" => false]);