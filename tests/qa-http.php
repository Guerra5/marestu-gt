<?php
declare(strict_types=1);

// Opt-in integration QA. Creates a NEW local database, never reuses/drops one.
// Usage: C:\xampp\php\php.exe tests/qa-http.php
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
$local = require $root . '/app/config/db.local.php';
if (!in_array($local['host'] ?? '', ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('QA only accepts a local database host.');
}
$name = 'marestu_qa_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$dir = $root . '/backups/qa/' . $name;
mkdir($dir, 0777, true);
mkdir($dir . '/sessions');
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$pdo = new PDO('mysql:host=127.0.0.1;port=' . (int)($local['port'] ?? 3306) . ';charset=utf8mb4', $local['username'], $local['password'], $options);
$source = (string)$local['database'];
if (!preg_match('/^[a-zA-Z0-9_]+$/', $source)) throw new RuntimeException('Invalid source schema.');
$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($pdo->query("SHOW TABLES FROM `$source`")->fetchAll(PDO::FETCH_COLUMN) as $table) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new RuntimeException('Invalid table.');
    $ddl = $pdo->query("SHOW CREATE TABLE `$source`.`$table`")->fetch(PDO::FETCH_NUM)[1];
    $pdo->exec(preg_replace('/AUTO_INCREMENT=\d+/', 'AUTO_INCREMENT=1', $ddl));
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$password = bin2hex(random_bytes(16));
$seed = $pdo->prepare('INSERT INTO usuarios(nombre,usuario,password_hash,rol,activo) VALUES(?,?,?,?,?)');
foreach ([['ADMIN',1], ['OPERADOR',1], ['INACTIVO',0]] as [$role,$active]) {
    $seed->execute(['QA ' . $role, 'qa_' . strtolower($role), password_hash($password, PASSWORD_BCRYPT), $role === 'ADMIN' ? 'ADMIN' : 'OPERADOR', $active]);
}
$results = [];
function check(bool $ok, string $label, mixed $evidence = null): void {
    global $results;
    $results[] = ['passed' => $ok, 'case' => $label, 'evidence' => $evidence];
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . ($ok || $evidence === null ? '' : ' ' . json_encode($evidence, JSON_UNESCAPED_UNICODE)) . "\n";
}
function rows(string $sql, array $params = []): array {
    global $pdo;
    $st = $pdo->prepare($sql); $st->execute($params); return $st->fetchAll();
}
function value(string $sql, array $params = []): mixed { return array_values(rows($sql, $params)[0])[0]; }
function snapshot(): string {
    $data = [];
    foreach (['reservas','reserva_detalle','reserva_extras','articulos','movimientos_inventario','clientes','categorias','usuarios'] as $table) {
        $data[$table] = rows("SELECT * FROM `$table` ORDER BY id");
    }
    return hash('sha256', json_encode($data));
}
$port = 18081;
$socket = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $error);
if (!$socket) throw new RuntimeException('QA port in use; refusing to contact an existing server.');
fclose($socket);
$env = getenv();
$env['DB_HOST'] = '127.0.0.1'; $env['DB_PORT'] = (string)($local['port'] ?? 3306);
$env['DB_NAME'] = $name; $env['DB_USER'] = $local['username'];
unset($env['DB_PASS']); // db.local.php supplies the local password, including an empty one.
$process = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-d', 'log_errors=1', '-d', 'session.save_path=' . $dir . '/sessions', '-S', "127.0.0.1:$port", '-t', $root . '/public'], [0=>['pipe','r'],1=>['file',$dir . '/server.log','a'],2=>['file',$dir . '/server.log','a']], $pipes, $root, $env);
if (!is_resource($process)) throw new RuntimeException('Could not start isolated QA server.');
fclose($pipes[0]);
$base = "http://127.0.0.1:$port/";
$requestCount = 0;
function request(string $who, string $path, ?array $data = null, bool $follow = true): array {
    global $dir, $base, $requestCount;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>$follow, CURLOPT_MAXREDIRS=>5, CURLOPT_TIMEOUT=>15, CURLOPT_COOKIEFILE=>$dir . '/' . $who . '.cookies', CURLOPT_COOKIEJAR=>$dir . '/' . $who . '.cookies']);
    if ($data !== null) curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);
    $body = curl_exec($ch);
    if ($body === false) throw new RuntimeException(curl_error($ch));
    $response = ['status'=>curl_getinfo($ch, CURLINFO_HTTP_CODE), 'url'=>curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), 'body'=>$body];
    curl_close($ch);
    $requestCount++;
    file_put_contents($dir . '/requests.jsonl', json_encode(['n'=>$requestCount,'who'=>$who,'method'=>$data===null?'GET':'POST','path'=>$path,'status'=>$response['status']], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    if (preg_match('/(?:Fatal error|Warning|Notice|Deprecated):|Uncaught (?:PDOException|Error)/i', $body)) {
        check(false, "PHP error on $path", strip_tags($body));
    }
    return $response;
}
function post(string $who, string $path, array $data): array {
    $page = request($who, 'index.php');
    if (!preg_match('/name="csrf"\s+value="([^"]+)"/', $page['body'], $match)) {
        // Dashboard may have no form; inventory is available to both roles.
        $page = request($who, 'categorias.php');
        if (!preg_match('/name="csrf"\s+value="([^"]+)"/', $page['body'], $match)) throw new RuntimeException('No CSRF token for ' . $who);
    }
    return request($who, $path, ['csrf'=>$match[1]] + $data);
}
function login(string $who, string $username, string $pass): array {
    $page = request($who, 'login.php');
    if (!preg_match('/name="csrf"\s+value="([^"]+)"/', $page['body'], $match)) throw new RuntimeException('Login form missing CSRF.');
    return request($who, 'login.php', ['csrf'=>$match[1], 'usuario'=>$username, 'password'=>$pass]);
}
function reserve(int $client, string $address = ''): int {
    $response = post('admin','reservas.php',['cliente_id'=>$client,'fecha_salida'=>date('Y-m-d',strtotime('+5 days')),'fecha_evento'=>date('Y-m-d',strtotime('+6 days')),'fecha_retorno'=>date('Y-m-d',strtotime('+7 days')),'direccion_evento'=>$address]);
    parse_str(parse_url($response['url'],PHP_URL_QUERY) ?? '', $query);
    if (empty($query['id'])) throw new RuntimeException('Reservation creation failed: ' . strip_tags($response['body']));
    return (int)$query['id'];
}
function state(int $id): string { return value('SELECT estado FROM reservas WHERE id=?',[$id]); }
try {
    for ($i=0;$i<40;$i++) { $ready=@fsockopen('127.0.0.1',$port,$e,$s,0.1); if ($ready) {fclose($ready);break;} usleep(100000); }
    echo "QA database: $name\n";
    // Apply exactly the additive migration to a pre-change schema, then repeat it.
    $pdo->exec('ALTER TABLE reservas DROP COLUMN direccion_evento');
    $migration = file_get_contents($root . '/migrations/20260921_direccion_evento.sql');
    $pdo->exec($migration); $pdo->exec($migration);
    check(count(rows("SHOW COLUMNS FROM reservas LIKE 'direccion_evento'")) === 1,'Migration works on old schema and is repeatable');
    check(str_contains(request('guest','reservas.php')['url'],'login.php'),'Anonymous user redirected to login');
    check(str_contains(login('bad','qa_admin','incorrect')['url'],'login.php'),'Wrong password rejected');
    check(str_contains(login('inactive','qa_inactivo',$password)['url'],'login.php'),'Inactive account rejected');
    foreach (['admin','operador'] as $who) {
        $response=login($who,'qa_' . $who,$password);
        $ok=str_contains($response['url'],'index.php');
        check($ok,"Login $who",$response['status']);
        if (!$ok) throw new RuntimeException('Authenticated session required before continuing QA.');
    }
    foreach (['index.php','clientes.php','categorias.php','articulos.php','reservas.php','kardex.php','calendario.php'] as $path) {
        foreach (['admin','operador'] as $who) { $page=request($who,$path); check($page['status']===200 && !str_contains($page['url'],'login.php'),"GET $path as $who"); }
    }
    foreach (['usuarios.php','usuario_nuevo.php','usuario_editar.php?id=1','clientes.php?edit=1'] as $path) check(request('operador',$path)['status']===403,"Operator denied $path");
    $before=snapshot();
    check(request('admin','clientes.php',['action'=>'create','nombres'=>'QA','apellidos'=>'Forbidden','telefono'=>'123'])['status']===403,'Missing CSRF rejected');
    check($before===snapshot(),'Missing CSRF writes nothing');
    post('admin','clientes.php',['action'=>'create','nombres'=>'QA','apellidos'=>'Cliente','telefono'=>'55551234','email'=>'qa@example.test','direccion'=>'Dirección fiscal QA','estado'=>'ACTIVO']);
    $client=(int)value('SELECT id FROM clientes LIMIT 1');
    check($client>0,'Admin creates customer in QA database');
    $before=snapshot();
    foreach (['create','update','toggle'] as $action) check(post('operador','clientes.php',['action'=>$action,'id'=>$client,'nombres'=>'No','apellidos'=>'Permitido','telefono'=>'123'])['status']===403,"Operator cannot $action customer");
    check(post('operador','reservas.php',['cliente_id'=>$client])['status']===403,'Operator cannot create reservation');
    check($before===snapshot(),'Denied customer/reservation writes leave database unchanged');
    post('operador','categorias.php',['action'=>'create','nombre'=>'QA Muebles','activo'=>1]);
    $cat=(int)value('SELECT id FROM categorias LIMIT 1');
    post('operador','categorias.php',['action'=>'update','id'=>$cat,'nombre'=>'QA Muebles editados','activo'=>1]);
    check(value('SELECT nombre FROM categorias WHERE id=?',[$cat])==='QA Muebles editados','Operator creates and edits category');
    foreach ([0,1] as $active) { post('operador','categorias.php',['action'=>'toggle','id'=>$cat]); check((int)value('SELECT activo FROM categorias WHERE id=?',[$cat])===$active,"Operator category toggle $active"); }
    $article=['nombre'=>'QA Mesa','categoria_id'=>$cat,'unidad'=>'pza','cantidad_total'=>20,'cantidad_activa'=>20,'precio_unitario'=>12.50];
    post('operador','articulos.php',['action'=>'create']+$article);
    $art=(int)value('SELECT id FROM articulos LIMIT 1');
    check((int)value('SELECT cantidad_activa FROM articulos WHERE id=?',[$art])===20,'Operator creates article with initial inventory');
    check((int)value("SELECT cantidad FROM movimientos_inventario WHERE articulo_id=? AND tipo='ENTRADA'",[$art])===20,'Initial inventory recorded in Kardex');
    post('operador','articulos.php',['action'=>'update','id'=>$art,'nombre'=>'QA Mesa editada','estado'=>'ACTIVO']+$article);
    check(value('SELECT nombre FROM articulos WHERE id=?',[$art])==='QA Mesa editada','Operator edits article');
    post('operador','articulos.php',['action'=>'stock','id'=>$art,'cantidad_total'=>22,'cantidad_activa'=>22,'nota'=>'QA ajuste']);
    check((int)value('SELECT cantidad_activa FROM articulos WHERE id=?',[$art])===22,'Manual inventory adjustment persists');
    check((int)value("SELECT cantidad FROM movimientos_inventario WHERE articulo_id=? AND tipo='AJUSTE' ORDER BY id DESC LIMIT 1",[$art])===2,'Manual adjustment records delta in Kardex');
    $before=snapshot(); post('operador','articulos.php',['action'=>'stock','id'=>$art,'cantidad_total'=>22,'cantidad_activa'=>23]); check($before===snapshot(),'Active inventory greater than total rejected');
    post('operador','categorias.php',['action'=>'toggle','id'=>$cat]); check((int)value('SELECT activo FROM categorias WHERE id=?',[$cat])===1,'Existing category rule: cannot deactivate with articles');
    $address="Salón QA & Jardín <central>\nZona 1, puerta norte";
    $r=reserve($client,$address); $edit="reserva_editar.php?id=$r"; $op="reserva_operacion.php?id=$r";
    check(value('SELECT direccion_evento FROM reservas WHERE id=?',[$r])===$address,'Event address stored independently');
    check(value('SELECT direccion FROM clientes WHERE id=?',[$client])==='Dirección fiscal QA','Customer address unchanged');
    $r2=reserve($client,'Otro salón QA');
    check(value('SELECT direccion_evento FROM reservas WHERE id=?',[$r2])==='Otro salón QA' && value('SELECT direccion_evento FROM reservas WHERE id=?',[$r])===$address,'Same customer, independent reservation addresses');
    $before=snapshot();
    foreach (['direccion_evento','add_item','remove_item','add_extra','remove_extra','set_status'] as $action) check(post('operador',$edit,['action'=>$action,'direccion_evento'=>'No permitido','to'=>'CONFIRMADA','articulo_id'=>$art,'cantidad'=>1])['status']===403,"Operator denied reservation action $action");
    check($before===snapshot(),'Denied reservation edits leave database unchanged');
    post('admin',$edit,['action'=>'set_status','to'=>'CONFIRMADA']); check(state($r)==='BORRADOR','Empty reservation cannot be confirmed');
    post('admin',$edit,['action'=>'add_item','articulo_id'=>$art,'cantidad'=>10]);
    $detail=(int)value('SELECT id FROM reserva_detalle WHERE reserva_id=?',[$r]);
    post('admin',$edit,['action'=>'add_extra','descripcion'=>'QA Transporte','cantidad'=>1,'precio_unitario'=>50,'proveedor'=>'QA']);
    $extra=(int)value('SELECT id FROM reserva_extras WHERE reserva_id=?',[$r]);
    check((int)value('SELECT cantidad FROM reserva_detalle WHERE id=?',[$detail])===10,'Reservation item persisted');
    check((float)value('SELECT subtotal FROM reserva_extras WHERE id=?',[$extra])===50.0,'Extra subtotal persisted');
    foreach (['reserva_nota_entrega.php','reserva_cotizacion.php'] as $path) {
        $page=request('operador',"$path?id=$r");
        check($page['status']===200 && str_contains($page['body'],'Salón QA &amp; Jardín &lt;central&gt;'),"Escaped event address in $path");
    }
    $quote=request('admin',"reserva_cotizacion.php?id=$r"); check(str_contains($quote['body'],'175.00'),'Quotation total: 10 x 12.50 + 50 = 175');
    post('admin',$edit,['action'=>'set_status','to'=>'CONFIRMADA']); check(state($r)==='CONFIRMADA','Confirm reservation');
    $page=request('operador',$op); check($page['status']===200 && str_contains($page['body'],'Salón QA &amp; Jardín &lt;central&gt;'),'Operator sees delivery address');
    $before=snapshot(); post('admin',"reserva_editar.php?id=$r2",['action'=>'add_item','articulo_id'=>$art,'cantidad'=>13]); check($before===snapshot(),'Overlapping availability blocks 13 when only 12 remain');
    $before=snapshot(); post('operador',$op,['action'=>'entregar',"entrega_$detail"=>11]); check($before===snapshot(),'Excess delivery leaves quantities and Kardex unchanged');
    post('operador',$op,['action'=>'entregar',"entrega_$detail"=>4]);
    check(state($r)==='CONFIRMADA' && (int)value('SELECT entregado FROM reserva_detalle WHERE id=?',[$detail])===4,'Partial delivery keeps reservation confirmed');
    $before=snapshot(); post('operador',$op,['action'=>'entregar',"entrega_$detail"=>1,"entrega_extra_$extra"=>2]); check($before===snapshot(),'Transaction rolls back first item when later extra exceeds pending');
    post('operador',$op,['action'=>'entregar',"entrega_$detail"=>6,"entrega_extra_$extra"=>1]);
    check(state($r)==='ENTREGADA','Full delivery including extra changes state');
    check((int)value("SELECT SUM(cantidad) FROM movimientos_inventario WHERE referencia_id=? AND tipo='SALIDA'",[$r])===-10,'Delivery Kardex totals -10');
    $before=snapshot(); post('operador',$op,['action'=>'entregar',"entrega_$detail"=>6]); check($before===snapshot(),'Repeated full delivery cannot duplicate movements');
    $before=snapshot(); post('operador',$op,['action'=>'devolver',"dev_$detail"=>11]); check($before===snapshot(),'Excess return rejected atomically');
    $before=snapshot(); post('operador',$op,['action'=>'devolver',"dev_$detail"=>-1]); check($before===snapshot(),'Negative return rejected');
    post('operador',$op,['action'=>'devolver',"dev_$detail"=>3]); check(state($r)==='ENTREGADA','Partial return remains delivered');
    post('operador',$op,['action'=>'devolver',"dev_$detail"=>5,"dan_$detail"=>1,"per_$detail"=>1,"dev_extra_$extra"=>1,'obs_bodega'=>'QA inspección']);
    check(state($r)==='DEVUELTA','Complete return with damage/loss closes reservation');
    $detailRow=rows('SELECT devuelto,danado,perdido FROM reserva_detalle WHERE id=?',[$detail])[0];
    check(array_map('intval',$detailRow)===['devuelto'=>8,'danado'=>1,'perdido'=>1],'Return breakdown persisted', $detailRow);
    check((int)value('SELECT cantidad_total FROM articulos WHERE id=?',[$art])===21 && (int)value('SELECT cantidad_activa FROM articulos WHERE id=?',[$art])===20,'Loss reduces total; damage and loss reduce active inventory');
    check((int)value("SELECT SUM(cantidad) FROM movimientos_inventario WHERE referencia_id=? AND tipo='DEVOLUCION'",[$r])===8,'Return Kardex totals +8');
    check((int)value("SELECT SUM(cantidad) FROM movimientos_inventario WHERE referencia_id=? AND tipo='AJUSTE'",[$r])===-2,'Damage/loss Kardex totals -2');
    $before=snapshot(); post('operador',$op,['action'=>'devolver',"dev_$detail"=>1]); post('admin',$edit,['action'=>'direccion_evento','direccion_evento'=>'No cambiar cerrado']); check($before===snapshot(),'Closed reservation cannot be returned again or address edited');
    $page=request('operador',"kardex.php?articulo_id=$art&tipo=SALIDA&q=QA"); check($page['status']===200 && str_contains($page['body'],'QA Mesa editada'),'Kardex filters render real movements');
    $calendar=request('operador','api_reservas.php?start='.date('Y-m-d').'&end='.date('Y-m-d',strtotime('+30 days'))); check($calendar['status']===200 && is_array(json_decode($calendar['body'],true)),'Calendar API returns valid JSON with real data');
    check(request('operador',"reserva_pdf.php?id=$r")['status']===403,'Operator cannot use admin PDF endpoint');
    $pdf=request('admin',"reserva_pdf.php?id=$r"); check($pdf['status']===200 && str_starts_with($pdf['body'],'%PDF'),'PDF generation available', ['status'=>$pdf['status'],'message'=>strip_tags($pdf['body'])]);
    if (str_starts_with($pdf['body'],'%PDF')) file_put_contents($dir.'/quotation-server.pdf',$pdf['body']);
    require_once $root.'/app/bootstrap.php';
    $document=new Marestu\Services\DocumentoService(new Marestu\Repositories\DocumentoRepository($pdo));
    $documentData=$document->data($r,'cotizacion');
    $documentHtml=Marestu\Http\View::render('reserva_pdf/index',$documentData->data+['pdfCss'=>'']);
    check(str_contains($documentHtml,'TOTAL A PAGAR: Q 175.00'),'Server PDF template uses real rental prices and extra total');
    check(str_contains($documentHtml,'FECHA EVENTO:</strong> '.date('Y-m-d',strtotime('+6 days'))),'Server PDF uses event date rather than delivery date');
    check(str_contains($documentHtml,'Salón QA &amp; Jardín &lt;central&gt;'),'Server PDF template includes escaped event address');
    $notePdf=request('admin',"reserva_pdf.php?id=$r&type=nota");
    check($notePdf['status']===200 && str_starts_with($notePdf['body'],'%PDF'),'Server delivery note PDF generation');
    if (str_starts_with($notePdf['body'],'%PDF')) file_put_contents($dir.'/delivery-note-server.pdf',$notePdf['body']);
    // Separate edge cases, after the successful main flow.
    post('admin',"reserva_editar.php?id=$r2",['action'=>'add_item','articulo_id'=>$art,'cantidad'=>15]);
    post('admin',"reserva_editar.php?id=$r2",['action'=>'set_status','to'=>'CONFIRMADA']);
    $r3=reserve($client); post('admin',"reserva_editar.php?id=$r3",['action'=>'add_item','articulo_id'=>$art,'cantidad'=>5]); post('admin',"reserva_editar.php?id=$r3",['action'=>'set_status','to'=>'CONFIRMADA']);
    check(state($r2)==='CONFIRMADA' && state($r3)==='CONFIRMADA','Availability permits exact remaining capacity');
    $before=snapshot(); post('admin',"reserva_operacion.php?id=$r3",['action'=>'entrega_adicional','tipo'=>'INV','articulo_id'=>$art,'cantidad_inv'=>1]);
    check($before===snapshot(),'Additional item cannot overbook capacity already fully reserved',rows("SELECT SUM(d.cantidad) AS reserved,a.cantidad_activa FROM reserva_detalle d JOIN reservas r ON r.id=d.reserva_id JOIN articulos a ON a.id=d.articulo_id WHERE a.id=? AND r.estado='CONFIRMADA' GROUP BY a.id",[$art]));
    post('admin',"reserva_editar.php?id=$r2",['action'=>'set_status','to'=>'CANCELADA']); check(state($r2)==='CANCELADA','Admin cancellation changes state');
    $before=snapshot(); post('operador',"reserva_operacion.php?id=$r2",['action'=>'entregar']); check($before===snapshot(),'Cancelled reservation cannot deliver');
    check(str_contains(request('admin','logout.php')['url'],'login.php') && str_contains(request('admin','index.php')['url'],'login.php'),'Logout removes authenticated access');
    file_put_contents($dir.'/browser.json',json_encode(['database'=>$name,'base'=>$base,'username'=>'qa_admin','password'=>$password,'reservation'=>$r,'article'=>$art,'category'=>$cat],JSON_PRETTY_PRINT));
} catch (Throwable $e) {
    check(false,'QA harness could not continue',$e->getMessage());
} finally {
    proc_terminate($process); proc_close($process);
    $summary=['database'=>$name,'php'=>PHP_VERSION,'mariadb'=>$pdo->query('SELECT VERSION()')->fetchColumn(),'requests'=>$requestCount,'passed'=>count(array_filter($results,fn($r)=>$r['passed'])),'failed'=>count(array_filter($results,fn($r)=>!$r['passed'])),'results'=>$results];
    file_put_contents($dir.'/results.json',json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    file_put_contents($root.'/backups/qa/latest.txt',$dir);
    echo json_encode(array_diff_key($summary,['results'=>true]),JSON_PRETTY_PRINT) . "\nResults: $dir\n";
}
exit($summary['failed'] > 0 ? 1 : 0);
