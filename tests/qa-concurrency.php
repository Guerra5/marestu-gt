<?php
declare(strict_types=1);
// Deterministic concurrency at the real service/repository boundary.
// Two PHP processes / PDO connections start the protected operation together.
// No production SQL or service code is changed; only scheduling is controlled.
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__);
require $root . '/app/bootstrap.php';
$dir=trim(file_get_contents($root . '/backups/qa/latest.txt'));
$meta=json_decode(file_get_contents($dir . '/browser.json'),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/^marestu_qa_\d{8}_\d{6}_[a-f0-9]{6}$/',$meta['database'])) throw new RuntimeException('Not a QA database.');
$local=require $root . '/app/config/db.local.php';
if (!in_array($local['host'],['127.0.0.1','localhost'],true)) throw new RuntimeException('Local only.');
$pdo=new PDO('mysql:host=127.0.0.1;port='.(int)($local['port']??3306).';dbname='.$meta['database'].';charset=utf8mb4',$local['username'],$local['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
function barrier(): void {
    global $argv;
    file_put_contents($argv[5].'.ready','ready');
    $deadline=microtime(true)+15;
    while (!is_file($argv[6])) { clearstatcache(); if(microtime(true)>$deadline) throw new RuntimeException('QA barrier timeout.'); usleep(10000); }
}
class QaOperationRepository extends Marestu\Repositories\ReservaOperacionRepository {
    public function withReservationTransaction(int $id, int $additionalArticle, callable $work): mixed {
        barrier();
        return parent::withReservationTransaction($id, $additionalArticle, $work);
    }
}
class QaConfirmationRepository extends Marestu\Repositories\ReservaEdicionRepository {
    public function withReservationTransaction(int $id, int $additionalArticle, callable $work): mixed {
        barrier();
        return parent::withReservationTransaction($id, $additionalArticle, $work);
    }
}
if (($argv[1]??'')==='worker') {
    $mode=$argv[2]; $reservation=(int)$argv[3]; $detail=(int)$argv[4];
    $actor=['id'=>1,'rol'=>'ADMIN'];
    if ($mode==='confirm') {
        $service=new Marestu\Services\ReservaEdicionService(new QaConfirmationRepository($pdo));
        $result=$service->execute(['id'=>$reservation],['action'=>'set_status','to'=>'CONFIRMADA'],$actor,true);
    } else {
        $service=new Marestu\Services\ReservaOperacionService(new QaOperationRepository($pdo));
        $input=$mode==='deliver'?['action'=>'entregar','entrega_'.$detail=>2]:['action'=>'devolver','dev_'.$detail=>2];
        $result=$service->execute(['id'=>$reservation],$input,$actor,true);
    }
    echo json_encode(['messages'=>$result->messages,'error'=>$result->data['error']??null],JSON_UNESCAPED_UNICODE);
    exit(0);
}
function insert(string $sql,array $params): int { global $pdo; $s=$pdo->prepare($sql);$s->execute($params);return (int)$pdo->lastInsertId(); }
function fixture(string $label,string $state,int $stock,int $quantity,int $delivered=0,?int $article=null): array {
    global $pdo;
    $suffix=bin2hex(random_bytes(4));
    if ($article===null) $article=insert('INSERT INTO articulos(codigo,nombre,categoria_id,cantidad_total,cantidad_activa,precio_unitario) VALUES(?,?,1,?,?,10)',['QA-'.$suffix,$label,$stock,$stock]);
    $r=insert('INSERT INTO reservas(codigo,cliente_id,fecha_salida,fecha_evento,fecha_retorno,estado,creado_por) VALUES(?,1,?,?,?,?,1)',['QAR-'.$suffix,date('Y-m-d',strtotime('+10 days')),date('Y-m-d',strtotime('+11 days')),date('Y-m-d',strtotime('+12 days')),$state]);
    $detail=insert('INSERT INTO reserva_detalle(reserva_id,articulo_id,cantidad,entregado) VALUES(?,?,?,?)',[$r,$article,$quantity,$delivered]);
    return [$r,$detail,$article];
}
$results=[];
foreach (['deliver','return','confirm'] as $mode) {
    $one=fixture('QA concurrency '.$mode,$mode==='confirm'?'BORRADOR':($mode==='deliver'?'CONFIRMADA':'ENTREGADA'),$mode==='confirm'?5:20,$mode==='confirm'?4:2,$mode==='return'?2:0);
    $two=$mode==='confirm'?fixture('QA concurrency confirm 2','BORRADOR',5,4,0,$one[2]):$one;
    $run=$dir.'/concurrency-'.$mode.'-'.bin2hex(random_bytes(3));
    $release=$run.'.release'; $children=[];
    try {
        // Hold the shared row until both workers enter, so this exercises lock waits.
        $pdo->beginTransaction();
        $hold=$pdo->prepare($mode==='confirm'?'SELECT id FROM articulos WHERE id=? FOR UPDATE':'SELECT id FROM reservas WHERE id=? FOR UPDATE');
        $hold->execute([$mode==='confirm'?$one[2]:$one[0]]); $hold->fetch();
        foreach ([$one,$two] as $i=>$fixture) {
            $prefix=$run.'-'.$i;
            $process=proc_open([PHP_BINARY,__FILE__,'worker',$mode,(string)$fixture[0],(string)$fixture[1],$prefix,$release],[0=>['pipe','r'],1=>['file',$prefix.'.log','w'],2=>['file',$prefix.'.err','w']],$pipes,$root);
            if(!is_resource($process)) throw new RuntimeException('Cannot launch worker.');
            fclose($pipes[0]); $children[]=$process;
        }
        $deadline=microtime(true)+10;
        while (!is_file($run.'-0.ready') || !is_file($run.'-1.ready')) {
            clearstatcache(); if(microtime(true)>$deadline) throw new RuntimeException('Workers did not reach the controlled read boundary.'); usleep(10000);
        }
        file_put_contents($release,'go');
        usleep(200000);
        $pdo->commit();
        foreach($children as $child) if(proc_close($child)!==0) throw new RuntimeException('Worker failed; inspect logs.');
        $children=[];
        if($mode==='confirm') {
            $s=$pdo->prepare("SELECT SUM(d.cantidad) AS reserved,a.cantidad_activa FROM reserva_detalle d JOIN reservas r ON r.id=d.reserva_id JOIN articulos a ON a.id=d.articulo_id WHERE a.id=? AND r.estado='CONFIRMADA' GROUP BY a.id");$s->execute([$one[2]]);$evidence=$s->fetch();
            $ok=(int)$evidence['reserved']===4;
        } else {
            $s=$pdo->prepare('SELECT cantidad,entregado,devuelto,danado,perdido FROM reserva_detalle WHERE id=?');$s->execute([$one[1]]);$evidence=$s->fetch();
            $ok=$mode==='deliver'?(int)$evidence['entregado']===2:(int)$evidence['devuelto']===2;
            $s=$pdo->prepare('SELECT SUM(cantidad) FROM movimientos_inventario WHERE referencia_id=? AND tipo=?');
            $s->execute([$one[0],$mode==='deliver'?'SALIDA':'DEVOLUCION']);
            $evidence['kardex']=(int)$s->fetchColumn();
            $ok=$ok && $evidence['kardex']===($mode==='deliver'?-2:2);
        }
        $results[]=['case'=>$mode,'passed'=>$ok,'evidence'=>$evidence,'reservation'=>$one[0]];
        echo ($ok?'PASS ':'FAIL ').$mode.' '.json_encode($evidence)."\n";
    } catch(Throwable $e) { $results[]=['case'=>$mode,'passed'=>false,'harness_error'=>$e->getMessage()]; echo 'HARNESS '.$e->getMessage()."\n"; }
    finally { if($pdo->inTransaction()) $pdo->rollBack(); foreach($children as $child) { if(is_resource($child)){proc_terminate($child);proc_close($child);} } }
}
file_put_contents($dir.'/concurrency-results.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
exit(count(array_filter($results,fn($r)=>!$r['passed']))?1:0);
