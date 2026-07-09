<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';

start_app_session();
require_login();

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

$start = (string)($_GET['start'] ?? '');
$end   = (string)($_GET['end'] ?? '');
$only  = (string)($_GET['only'] ?? ''); // opcional: 'open' para solo CONFIRMADA/ENTREGADA

// Validación básica (FullCalendar manda ISO, a veces con hora)
$startDate = substr($start, 0, 10);
$endDate   = substr($end, 0, 10);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
  echo json_encode([]);
  exit;
}

$states = ['BORRADOR','CONFIRMADA','ENTREGADA','DEVUELTA','CANCELADA'];

$whereEstado = "";
$params = [$endDate, $startDate];

if ($only === 'open') {
  $whereEstado = " AND r.estado IN ('CONFIRMADA','ENTREGADA') ";
} else {
  // por defecto mostramos todo menos canceladas (más útil)
  $whereEstado = " AND r.estado <> 'CANCELADA' ";
}

$sql = "
  SELECT r.id, r.codigo, r.fecha_salida, r.fecha_retorno, r.estado,
         CONCAT(c.nombres,' ',c.apellidos) AS cliente, c.telefono
  FROM reservas r
  INNER JOIN clientes c ON c.id = r.cliente_id
  WHERE r.fecha_salida <= ?
    AND r.fecha_retorno >= ?
    $whereEstado
  ORDER BY r.fecha_salida ASC, r.id ASC
";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function color_by_estado(string $estado): array {
  // colores simples (podés ajustarlos)
  switch ($estado) {
    case 'CONFIRMADA': return ['bg'=>'#0d6efd', 'txt'=>'#ffffff'];
    case 'ENTREGADA':  return ['bg'=>'#212529', 'txt'=>'#ffffff'];
    case 'DEVUELTA':   return ['bg'=>'#198754', 'txt'=>'#ffffff'];
    case 'BORRADOR':   return ['bg'=>'#6c757d', 'txt'=>'#ffffff'];
    case 'CANCELADA':  return ['bg'=>'#dc3545', 'txt'=>'#ffffff'];
    default:           return ['bg'=>'#0d6efd', 'txt'=>'#ffffff'];
  }
}

$events = [];

foreach ($rows as $r) {
  $estado = (string)$r['estado'];
  $c = color_by_estado($estado);

  $start = (string)$r['fecha_salida'];
  $end = date('Y-m-d', strtotime(((string)$r['fecha_retorno']) . ' +1 day')); // end exclusivo

  $title = $r['codigo'] . " · " . $r['cliente'];

  $events[] = [
    'id' => (int)$r['id'],
    'title' => $title,
    'start' => $start,
    'end' => $end,
    'allDay' => true,
    'backgroundColor' => $c['bg'],
    'borderColor' => $c['bg'],
    'textColor' => $c['txt'],
    'extendedProps' => [
      'estado' => $estado,
      'telefono' => (string)$r['telefono'],
      'codigo' => (string)$r['codigo'],
      'cliente' => (string)$r['cliente'],
      'salida' => (string)$r['fecha_salida'],
      'retorno' => (string)$r['fecha_retorno'],
    ],
  ];
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);
