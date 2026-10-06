<?php
$html = '
<html>
<head>
<style>' . $pdfCss . '</style>
</head>
<body>
    <div class="header">
        <h1>Marestu</h1>
        <div data-style="reserva_pdf-1">EVENTOS</div>
        <div>cori2leon@gmail.com | Tel. 58599321 - 54123635</div>
    </div>

    <div data-style="reserva_pdf-2">
        ' . ($type === 'nota' ? 'NOTA DE ENVÍO' : 'COTIZACIÓN') . '
    </div>

    <table class="info-table">
        <tr>
            <td><strong>CLIENTE:</strong> ' . htmlspecialchars($res['nombres'] . ' ' . $res['apellidos']) . '</td>
            <td><strong>FECHA ENTREGA:</strong> ' . $res['fecha_salida'] . '</td>
        </tr>
        <tr>
            <td><strong>DIRECCIÓN:</strong> ' . htmlspecialchars($res['direccion_evento'] ?? $res['direccion'] ?? 'N/A') . '</td>
            <td><strong>FECHA EVENTO:</strong> ' . ($res['fecha_evento'] ?? $res['fecha_salida']) . '</td>
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
                <td>' . (($it['precio_reposicion'] ?? 0) > 0 ? 'Q '.number_format((float)$it['precio_reposicion'], 2) : 'Reparación') . '</td>' : '') . '
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

    <table data-style="reserva_pdf-3">
        <tr>
            <td data-style="reserva_pdf-4">Ingrid de León - Asesora</td>
            <td data-style="reserva_pdf-5"></td>
            <td data-style="reserva_pdf-4">Aceptado por el cliente</td>
        </tr>
    </table>
</body>
</html>';


return $html;
