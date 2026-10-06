<?php
declare(strict_types=1);
// A busy reservation must fail without writes, then succeed after the lock is released.
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__);
require $root.'/app/bootstrap.php';
$dir=trim(file_get_contents($root.'/backups/qa/latest.txt'));
$meta=json_decode(file_get_contents($dir.'/browser.json'),true,512,JSON_THROW_ON_ERROR);
$local=require $root.'/app/config/db.local.php';
if(!preg_match('/^marestu_qa_\d{8}_\d{6}_[a-f0-9]{6}$/',$meta['database']) || !in_array($local['host'],['localhost','127.0.0.1'],true)) throw new RuntimeException('Only local QA databases are allowed.');
$dsn='mysql:host=127.0.0.1;port='.(int)($local['port']??3306).';dbname='.$meta['database'].';charset=utf8mb4';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$holder=new PDO($dsn,$local['username'],$local['password'],$options);
$worker=new PDO($dsn,$local['username'],$local['password'],$options);
$code='QAT-'.bin2hex(random_bytes(4));
$s=$holder->prepare("INSERT INTO reservas(codigo,cliente_id,fecha_salida,fecha_evento,fecha_retorno,estado,creado_por) VALUES(?,1,CURRENT_DATE,CURRENT_DATE,CURRENT_DATE,'CONFIRMADA',1)");$s->execute([$code]);$r=(int)$holder->lastInsertId();
$s=$holder->prepare('INSERT INTO reserva_detalle(reserva_id,articulo_id,cantidad) VALUES(?, ?, 2)');$s->execute([$r,$meta['article']]);$d=(int)$holder->lastInsertId();
$service=new Marestu\Services\ReservaOperacionService(new Marestu\Repositories\ReservaOperacionRepository($worker));
$holder->beginTransaction();
$s=$holder->prepare('SELECT id FROM reservas WHERE id=? FOR UPDATE');$s->execute([$r]);$s->fetch();
$results=[];
try {
    $start=microtime(true);
    $result=$service->execute(['id'=>$r],['action'=>'entregar','entrega_'.$d=>2],['id'=>1,'rol'=>'ADMIN'],true);
    $elapsed=microtime(true)-$start;
    $s=$holder->prepare('SELECT entregado FROM reserva_detalle WHERE id=?');$s->execute([$d]);
    $ok=isset($result->messages['err']) && (int)$s->fetchColumn()===0 && !$worker->inTransaction() && $elapsed<10;
    $results[]=['case'=>'Busy reservation fails without writes or leaked transaction','passed'=>$ok,'seconds'=>$elapsed];
} finally { $holder->rollBack(); }
$result=$service->execute(['id'=>$r],['action'=>'entregar','entrega_'.$d=>2],['id'=>1,'rol'=>'ADMIN'],true);
$s=$holder->prepare("SELECT SUM(cantidad) FROM movimientos_inventario WHERE referencia_id=? AND tipo='SALIDA'");$s->execute([$r]);
$results[]=['case'=>'Same repository can retry successfully after timeout','passed'=>isset($result->messages['ok']) && (int)$s->fetchColumn()===-2 && !$worker->inTransaction()];
foreach($results as $result) echo ($result['passed']?'PASS ':'FAIL ').$result['case']."\n";
file_put_contents($dir.'/lock-timeout-results.json',json_encode($results,JSON_PRETTY_PRINT));
exit(count(array_filter($results,fn($r)=>!$r['passed']))?1:0);
