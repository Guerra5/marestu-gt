<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';

// Las solicitudes prohibidas deben detenerse antes de abrir la base de datos.
if (($argv[1] ?? '') === 'request') {
  start_app_session();
  $_SESSION['user'] = ['id' => 1, 'rol' => 'OPERADOR'];
  $_SERVER['REQUEST_METHOD'] = $argv[3];
  $_POST = ['action' => $argv[4] ?? ''];
  $_GET = ['edit' => 1, 'id' => 1];
  register_shutdown_function(static function (): void {
    if (http_response_code() !== 403) {
      fwrite(STDERR, 'Se esperaba HTTP 403.');
      exit(1);
    }
  });
  require __DIR__ . '/../public/' . $argv[2];
  exit(1);
}

$expected = [
  'ADMIN' => [true, true, true, true, true, true, true],
  'OPERADOR' => [true, false, true, false, true, true, true],
  'DESCONOCIDO' => [false, false, false, false, false, false, false],
  '' => [false, false, false, false, false, false, false],
];
$permissions = ['clientes.ver', 'clientes.gestionar', 'reservas.ver', 'reservas.gestionar', 'reservas.operar', 'inventario.gestionar', 'kardex.ver'];
$checks = 0;
foreach ($expected as $role => $values) {
  $_SESSION['user'] = ['rol' => $role];
  foreach ($permissions as $i => $permission) {
    if (can($permission) !== $values[$i]) {
      throw new RuntimeException("Permiso incorrecto: {$role}/{$permission}");
    }
    $checks++;
  }
  if (can('permiso.inexistente')) {
    throw new RuntimeException('Un permiso desconocido debe denegarse.');
  }
}
$requests = [['clientes.php', 'POST', 'create'], ['clientes.php', 'GET', ''], ['reservas.php', 'POST', ''], ['reserva_editar.php', 'POST', 'direccion_evento']];
foreach (['editar_item_pedido', 'eliminar_item_pedido', 'editar_extra_pedido', 'eliminar_extra_pedido', 'entrega_adicional'] as $action) {
  $requests[] = ['reserva_operacion.php', 'POST', $action];
}
foreach ($requests as [$page, $method, $action]) {
  $process = proc_open([PHP_BINARY, '-n', __FILE__, 'request', $page, $method, $action], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
  if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar la prueba HTTP.');
  $output = stream_get_contents($pipes[1]);
  $error = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  if (proc_close($process) !== 0 || !str_contains($output, 'No autorizado')) {
    throw new RuntimeException("Falló {$method} {$page}: {$output} {$error}");
  }
  $checks++;
}
echo "OK: {$checks} comprobaciones de permisos y acceso directo.\n";
