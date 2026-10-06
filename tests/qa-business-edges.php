<?php
declare(strict_types=1);
// Extra business invariants against the isolated QA database, using real services.
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__);
require $root.'/app/bootstrap.php';
$dir=trim(file_get_contents($root.'/backups/qa/latest.txt'));
$meta=json_decode(file_get_contents($dir.'/browser.json'),true,512,JSON_THROW_ON_ERROR);
if(!preg_match('/^marestu_qa_\d{8}_\d{6}_[a-f0-9]{6}$/',$meta['database'])) throw new RuntimeException('Not a QA database.');
$local=require $root.'/app/config/db.local.php';
if(!in_array($local['host'],['localhost','127.0.0.1'],true)) throw new RuntimeException('Local only.');
$pdo=new PDO('mysql:host=127.0.0.1;port='.(int)($local['port']??3306).';dbname='.$meta['database'].';charset=utf8mb4',$local['username'],$local['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$edit=new Marestu\Services\ReservaEdicionService(new Marestu\Repositories\ReservaEdicionRepository($pdo));
$operation=new Marestu\Services\ReservaOperacionService(new Marestu\Repositories\ReservaOperacionRepository($pdo));
$actor=['id'=>1,'rol'=>'ADMIN'];
function query(string $sql,array $params=[]): PDOStatement {global $pdo;$s=$pdo->prepare($sql);$s->execute($params);return $s;}
function fixture(string $state,int $quantity,int $delivered=0,?int $article=null): array {
    global $pdo;
    $code=bin2hex(random_bytes(4));
    if($article===null){query('INSERT INTO articulos(codigo,nombre,categoria_id,cantidad_total,cantidad_activa,precio_unitario) VALUES(?,?,1,20,20,10)',['QAE-'.$code,'QA borde '.$code]);$article=(int)$pdo->lastInsertId();}
    query('INSERT INTO reservas(codigo,cliente_id,fecha_salida,fecha_evento,fecha_retorno,estado,creado_por) VALUES(?,1,?,?,?,?,1)',['QAE-'.$code,date('Y-m-d',strtotime('+20 days')),date('Y-m-d',strtotime('+21 days')),date('Y-m-d',strtotime('+22 days')),$state]);$r=(int)$pdo->lastInsertId();
    query('INSERT INTO reserva_detalle(reserva_id,articulo_id,cantidad,entregado) VALUES(?,?,?,?)',[$r,$article,$quantity,$delivered]);
    return [$r,(int)$pdo->lastInsertId(),$article];
}
$results=[];
function check(bool $ok,string $case,array $evidence): void {global $results;$results[]=['passed'=>$ok,'case'=>$case,'evidence'=>$evidence];echo ($ok?'PASS ':'FAIL ').$case.' '.json_encode($evidence)."\n";}
[$r,$d,$a]=fixture('CONFIRMADA',10);
fixture('CONFIRMADA',10,0,$a);
$operation->execute(['id'=>$r],['action'=>'editar_item_pedido','detalle_id'=>$d,'nueva_cantidad'=>11],$actor,true);
$reserved=(int)query("SELECT SUM(d.cantidad) FROM reserva_detalle d JOIN reservas r ON r.id=d.reserva_id WHERE d.articulo_id=? AND r.estado='CONFIRMADA'",[$a])->fetchColumn();
check($reserved<=20,'Editing confirmed item must respect other reservations',['reserved'=>$reserved,'active'=>20,'reservation'=>$r]);
[$r,$d,$a]=fixture('BORRADOR',20);
$edit->execute(['id'=>$r],['action'=>'add_item','articulo_id'=>$a,'cantidad'=>40],$actor,true);
$quantity=(int)query('SELECT cantidad FROM reserva_detalle WHERE id=?',[$d])->fetchColumn();
check($quantity<=20,'Replacing draft quantity must not count existing draft twice',['quantity'=>$quantity,'active'=>20,'reservation'=>$r]);
// Seed an oversized legacy draft separately; the preceding edit is now rejected.
query('UPDATE reserva_detalle SET cantidad=40 WHERE id=?',[$d]);
$edit->execute(['id'=>$r],['action'=>'set_status','to'=>'CONFIRMADA'],$actor,true);
check(query('SELECT estado FROM reservas WHERE id=?',[$r])->fetchColumn()==='BORRADOR','Confirmation still blocks oversized draft',['reservation'=>$r]);
[$r,$d,$a]=fixture('CONFIRMADA',10);
$operation->execute(['id'=>$r],['action'=>'entregar','entrega_'.$d=>2],$actor,true);
$edit->execute(['id'=>$r],['action'=>'set_status','to'=>'CANCELADA'],$actor,true);
$operation->execute(['id'=>$r],['action'=>'devolver','dev_'.$d=>2],$actor,true);
$data=query('SELECT r.estado,d.entregado,d.devuelto FROM reservas r JOIN reserva_detalle d ON d.reserva_id=r.id WHERE r.id=?',[$r])->fetch();
check($data['estado']!=='CANCELADA' || (int)$data['entregado']===(int)$data['devuelto'],'Cancellation must not strand items already delivered',$data+['reservation'=>$r]);
file_put_contents($dir.'/business-edge-results.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
exit(count(array_filter($results,fn($r)=>!$r['passed']))?1:0);
