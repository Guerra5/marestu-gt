<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/Support/MemoryPdo.php';

use Marestu\Http\View;

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

$actor = ['id' => 1, 'nombre' => 'Prueba', 'usuario' => 'prueba', 'rol' => 'ADMIN'];
$_SESSION = ['user' => $actor, 'csrf' => 'test-csrf'];
$_SERVER['PHP_SELF'] = '/index.php';
$_GET = [];
$record = [
    'id' => 1, 'codigo' => 'RES-000001', 'nombre' => 'Artículo de prueba', 'nombres' => 'Ana', 'apellidos' => 'Prueba',
    'cliente' => 'Ana Prueba', 'telefono' => '55555555', 'email' => 'prueba@example.test', 'nit' => 'CF',
    'direccion' => 'Dirección del cliente', 'direccion_cliente' => 'Dirección del cliente', 'direccion_evento' => 'Salón & Jardín <central>',
    'cliente_id' => 1, 'categoria_id' => 1, 'articulo_id' => 1, 'categoria' => 'Mesas', 'categoria_nombre' => 'Mesas', 'categoria_activa' => 1,
    'estado' => 'CONFIRMADA', 'activo' => 1, 'rol' => 'OPERADOR', 'usuario' => 'prueba', 'password_hash' => password_hash('secreto123', PASSWORD_BCRYPT),
    'fecha_salida' => date('Y-m-d'), 'fecha_evento' => date('Y-m-d'), 'fecha_retorno' => date('Y-m-d'), 'creado_en' => '2026-09-21 10:00:00',
    'cantidad' => 10, 'entregado' => 5, 'devuelto' => 1, 'danado' => 0, 'perdido' => 0, 'cantidad_total' => 100, 'cantidad_activa' => 95,
    'precio_unitario' => '10.00', 'precio_reposicion' => '50.00', 'subtotal' => '100.00', 'unidad' => 'pza', 'ubicacion' => 'Bodega',
    'descripcion' => 'Transporte', 'proveedor' => 'Proveedor', 'nota' => 'Nota de prueba', 'observaciones' => '', 'obs_bodega' => '', 'obs_entrega' => '',
    'obs_devolucion' => '', 'observaciones_devolucion' => '', 'n' => 1, 'qty' => 10, 'total' => 0, 'articulos' => 1,
    'pend_entrega' => 5, 'pend_devol' => 4, 'entradas' => 10, 'salidas' => 5, 'neto' => 5,
    'articulo_codigo' => 'ART-000001', 'articulo_nombre' => 'Mesa', 'usuario_nombre' => 'Prueba', 'usuario_user' => 'prueba',
    'reserva_codigo' => 'RES-000001', 'tipo' => 'SALIDA', 'referencia_tipo' => 'RESERVA', 'referencia_id' => 1,
];
$database = new MemoryPdo(static fn (string $sql, array $params): array => str_starts_with(ltrim(strtoupper($sql)), 'SELECT') ? [$record] : []);
$pages = [
    'Dashboard' => 'index', 'Clientes' => 'clientes', 'Categorias' => 'categorias', 'Articulos' => 'articulos',
    'Reservas' => 'reservas', 'ReservaEdicion' => 'reserva_editar', 'ReservaOperacion' => 'reserva_operacion',
    'Usuarios' => 'usuarios', 'UsuarioNuevo' => 'usuario_nuevo', 'UsuarioEdicion' => 'usuario_editar',
    'Kardex' => 'kardex', 'Cotizacion' => 'reserva_cotizacion', 'NotaEntrega' => 'reserva_nota_entrega', 'Login' => 'login', 'Calendario' => 'calendario',
];
foreach ($pages as $module => $page) {
    $repoClass = 'Marestu\\Repositories\\' . $module . 'Repository';
    $serviceClass = 'Marestu\\Services\\' . $module . 'Service';
    $service = new $serviceClass(new $repoClass($database));
    $result = $service->execute(['id' => 1, 'edit' => 1], [], $actor, false);
    check($result->destination === null && $result->errorBody === null, 'Carga fallida: ' . $module);
    $html = View::render($page . '/index', $result->data + ['alertas' => 0]);
    check(str_contains($html, '<html') && str_contains($html, '</html>'), 'Vista incompleta: ' . $module);
    check(!str_contains($html, '<script>') && !str_contains($html, 'onclick='), 'JS incrustado: ' . $module);
}

// Escenarios que cambian formularios y permisos visuales.
foreach (['ADMIN', 'OPERADOR'] as $role) {
    foreach (['BORRADOR', 'CONFIRMADA', 'ENTREGADA', 'DEVUELTA', 'CANCELADA'] as $state) {
        $row = array_replace($record, ['estado' => $state]);
        $db = new MemoryPdo(static fn (string $sql): array => [$row]);
        $user = array_replace($actor, ['rol' => $role]);
        $_SESSION['user'] = $user;
        foreach (['ReservaEdicion' => 'reserva_editar', 'ReservaOperacion' => 'reserva_operacion'] as $module => $page) {
            $repoClass = 'Marestu\\Repositories\\' . $module . 'Repository';
            $serviceClass = 'Marestu\\Services\\' . $module . 'Service';
            $result = (new $serviceClass(new $repoClass($db)))->execute(['id' => 1], [], $user, false);
            if ($state === 'BORRADOR' && $module === 'ReservaOperacion') {
                check($result->destination === 'reserva_editar.php?id=1', 'Borrador debe volver al detalle.');
                continue;
            }
            $html = View::render($page . '/index', $result->data + ['alertas' => 0]);
            check(str_contains($html, 'Salón &amp; Jardín &lt;central&gt;'), 'Dirección ausente o sin escape.');
            if ($role === 'OPERADOR') {
                check(!str_contains($html, 'value="set_status"') && !str_contains($html, 'value="direccion_evento"'), 'Operador ve administración de reserva.');
            }
        }
    }
}
$_SESSION['user'] = $actor;

// Dirección: escritura independiente y límites de estado.
$db = new MemoryPdo(static fn (): array => []);
$service = new Marestu\Services\ReservaEdicionService(new Marestu\Repositories\ReservaEdicionRepository($db));
$context = ['id' => 7, 'res' => ['estado' => 'BORRADOR'], 'error' => null];
$result = $service->direccion_evento(['direccion_evento' => '  Lugar distinto  '], $context, $actor);
check($result->destination === 'reserva_editar.php?id=7', 'Redirección de dirección.');
check($db->calls[0]['params'] === ['Lugar distinto', 7], 'Dirección no normalizada o reserva equivocada.');
check(!preg_match('/UPDATE\s+clientes/i', $db->calls[0]['sql']), 'No debe modificar el cliente.');
$service->direccion_evento(['direccion_evento' => ''], $context, $actor);
check($db->calls[1]['params'] === [null, 7], 'Dirección vacía debe guardarse NULL.');
$before = count($db->calls);
$result = $service->direccion_evento(['direccion_evento' => 'No cambiar'], ['id' => 7, 'res' => ['estado' => 'DEVUELTA']], $actor);
check(count($db->calls) === $before && isset($result->messages['err']), 'No editar una reserva cerrada.');

// Crear una reserva conserva la nueva dirección, fechas y transacción.
$db = new MemoryPdo(static fn (string $sql): array => str_starts_with(ltrim($sql), 'SELECT') ? [['id' => 1]] : []);
$service = new Marestu\Services\ReservasService(new Marestu\Repositories\ReservasRepository($db));
$result = $service->execute([], ['cliente_id' => 1, 'fecha_salida' => date('Y-m-d'), 'fecha_evento' => date('Y-m-d'), 'fecha_retorno' => date('Y-m-d'), 'direccion_evento' => 'Jardín de prueba'], $actor, true);
check($result->destination === 'reserva_editar.php?id=42', 'Crear reserva debe ir al detalle.');
check($db->transactions === ['begin', 'commit'], 'Creación sin transacción completa.');
$insert = array_values(array_filter($db->calls, static fn ($call) => str_contains($call['sql'], 'INSERT INTO reservas')))[0];
check(end($insert['params']) === 'Jardín de prueba', 'Falta dirección en INSERT.');

// Login correcto, incorrecto e inactivo; nunca devolver el hash a la vista.
$db = new MemoryPdo(static fn (): array => [$record]);
$service = new Marestu\Services\LoginService(new Marestu\Repositories\LoginRepository($db));
$result = $service->execute([], ['usuario' => 'prueba', 'password' => 'secreto123'], [], true);
check($result->destination === 'index.php' && $result->authenticatedUser['id'] === 1, 'Login correcto falló.');
$result = $service->execute([], ['usuario' => 'prueba', 'password' => 'incorrecta'], [], true);
check($result->authenticatedUser === null && !empty($result->data['error']), 'Contraseña incorrecta aceptada.');
check(!str_contains(json_encode($result->data), $record['password_hash']), 'Hash expuesto a la vista.');
$inactive = array_replace($record, ['activo' => 0]);
$service = new Marestu\Services\LoginService(new Marestu\Repositories\LoginRepository(new MemoryPdo(static fn (): array => [$inactive])));
$result = $service->execute([], ['usuario' => 'prueba', 'password' => 'secreto123'], [], true);
check($result->authenticatedUser === null && !empty($result->data['error']), 'Usuario inactivo aceptado.');

// Entrega completa: cantidades, kardex, estado final y commit.
$delivered = array_replace($record, ['entregado' => 10]);
$resolver = static function (string $sql) use ($delivered): array {
    if (preg_match('/FROM\s+reserva_extras/i', $sql)) return [];
    return preg_match('/^\s*SELECT/i', $sql) ? [$delivered] : [];
};
$db = new MemoryPdo($resolver);
$service = new Marestu\Services\ReservaOperacionService(new Marestu\Repositories\ReservaOperacionRepository($db));
$context = ['id' => 9, 'res' => array_replace($record, ['estado' => 'CONFIRMADA']), 'items' => [$record], 'extras' => []];
$result = $service->entregar(['entrega_1' => 5], $context, $actor);
check($result->destination === 'reserva_operacion.php?id=9' && isset($result->messages['ok']), 'Entrega no terminó correctamente.');
check($db->transactions === ['begin', 'commit'], 'Entrega debe confirmar su transacción.');
$calls = $db->calls;
check(count(array_filter($calls, static fn ($call) => $call['params'] === ['ENTREGADA', 9])) === 1, 'Entrega completa no cambió el estado.');
$moves = array_values(array_filter($calls, static fn ($call) => str_contains($call['sql'], 'INSERT INTO movimientos_inventario')));
check(count($moves) === 1 && $moves[0]['params'][0] === 'SALIDA' && $moves[0]['params'][3] === -5, 'Kardex de entrega incorrecto.');

// Un exceso de entrega no escribe cantidades y revierte la transacción.
$db = new MemoryPdo($resolver);
$service = new Marestu\Services\ReservaOperacionService(new Marestu\Repositories\ReservaOperacionRepository($db));
$result = $service->entregar(['entrega_1' => 6], $context, $actor);
check($db->transactions === ['begin', 'rollback'] && !empty($result->data['error']), 'Exceso de entrega no se revierte.');
check(!array_filter($db->calls, static fn ($call) => preg_match('/^\s*(UPDATE|INSERT)/', $call['sql'])), 'Exceso escribió inventario.');

// Devolución con daños y pérdidas: preserva las tres clases de movimiento.
$closed = array_replace($record, ['entregado' => 10, 'devuelto' => 8, 'danado' => 1, 'perdido' => 1]);
$db = new MemoryPdo(static function (string $sql) use ($closed): array {
    if (preg_match('/FROM\s+reserva_extras/i', $sql)) return [];
    return preg_match('/^\s*SELECT/i', $sql) ? [$closed] : [];
});
$service = new Marestu\Services\ReservaOperacionService(new Marestu\Repositories\ReservaOperacionRepository($db));
$context = ['id' => 9, 'res' => array_replace($record, ['estado' => 'ENTREGADA']), 'items' => [array_replace($record, ['entregado' => 10])], 'extras' => []];
$result = $service->devolver(['dev_1' => 7, 'dan_1' => 1, 'per_1' => 1, 'obs_bodega' => 'Revisión'], $context, $actor);
check($result->destination === 'reserva_operacion.php?id=9' && isset($result->messages['ok']), 'Devolución no terminó correctamente.');
check($db->transactions === ['begin', 'commit'], 'Devolución debe confirmar su transacción.');
$moves = array_values(array_filter($db->calls, static fn ($call) => str_contains($call['sql'], 'INSERT INTO movimientos_inventario')));
check(count($moves) === 3, 'Faltan movimientos de devolución/daño/pérdida.');
check(array_column(array_column($moves, 'params'), 3) === [7, -1, -1], 'Cantidades incorrectas en kardex.');
check(count(array_filter($db->calls, static fn ($call) => $call['params'] === ['DEVUELTA', 9])) === 1, 'Devolución completa no cerró la reserva.');

// Error del repositorio durante entrega: propaga el error y revierte.
$db = new MemoryPdo(static function (string $sql): array {
    if (str_contains($sql, 'INSERT INTO movimientos_inventario')) throw new RuntimeException('Fallo simulado de persistencia');
    return [];
});
$service = new Marestu\Services\ReservaOperacionService(new Marestu\Repositories\ReservaOperacionRepository($db));
$context['res']['estado'] = 'CONFIRMADA';
$context['items'] = [$record];
$result = $service->entregar(['entrega_1' => 1], $context, $actor);
check($db->transactions === ['begin', 'rollback'] && $result->data['error'] === 'Fallo simulado de persistencia', 'Fallo de persistencia no revierte.');

// El calendario conserva el final exclusivo y las propiedades del evento.
$calendar = new Marestu\Services\CalendarioEventosService(new Marestu\Repositories\CalendarioEventosRepository($database));
$events = $calendar->events(['start' => '2026-09-01', 'end' => '2026-10-01', 'only' => 'open']);
check(count($events) === 1 && $events[0]['extendedProps']['codigo'] === $record['codigo'], 'Evento de calendario incorrecto.');
check($events[0]['end'] === date('Y-m-d', strtotime($record['fecha_retorno'] . ' +1 day')), 'Calendario perdió su final exclusivo.');
check($calendar->events(['start' => 'inválida', 'end' => '']) === [], 'Fechas inválidas del calendario.');

// Plantilla PDF desacoplada de Dompdf: se puede validar sin instalar el motor.
$document = new Marestu\Services\DocumentoService(new Marestu\Repositories\DocumentoRepository($database));
$result = $document->data(1, 'cotizacion');
$html = View::render('reserva_pdf/index', $result->data + ['pdfCss' => 'body { color: black; }']);
check(str_contains($html, 'Salón &amp; Jardín &lt;central&gt;') && str_contains($html, 'TOTAL A PAGAR'), 'Documento PDF perdió dirección o total.');

echo "OK: {$checks} comprobaciones de servicios y renderizado.\n";
