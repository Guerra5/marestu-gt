<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';

start_app_session();

$pdo = db();

// Cambiá esto si querés
$admin_user = 'admin';
$admin_pass = 'admin123';
$admin_name = 'Administrador';

$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
$stmt->execute([$admin_user]);
$exists = $stmt->fetch();

if ($exists) {
  echo "<h3>✅ Ya existe el usuario admin.</h3><p>Podés ir a <a href='login.php'>login</a>.</p>";
  exit;
}

$hash = password_hash($admin_pass, PASSWORD_BCRYPT);

$ins = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo) VALUES (?,?,?,?,1)");
$ins->execute([$admin_name, $admin_user, $hash, 'ADMIN']);

echo "<h3>✅ Admin creado</h3>";
echo "<p>Usuario: <b>{$admin_user}</b></p>";
echo "<p>Pass: <b>{$admin_pass}</b></p>";
echo "<p>👉 Entrá a <a href='login.php'>login</a> y luego BORRÁ instalar.php</p>";
