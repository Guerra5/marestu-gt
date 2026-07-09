<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

start_app_session();
require_login();

if (!is_admin()) {
  http_response_code(403);
  echo "No autorizado.";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}

if (!csrf_validate($_POST['csrf'] ?? null)) {
  flash_set('err', "Token inválido.");
  header('Location: usuarios.php');
  exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  flash_set('err', "ID inválido.");
  header('Location: usuarios.php');
  exit;
}

if ($id === (int)current_user()['id']) {
  flash_set('err', "No podés desactivar tu propio usuario.");
  header('Location: usuarios.php');
  exit;
}

$pdo = db();
$st = $pdo->prepare("SELECT activo FROM usuarios WHERE id = ? LIMIT 1");
$st->execute([$id]);
$row = $st->fetch();

if (!$row) {
  flash_set('err', "Usuario no encontrado.");
  header('Location: usuarios.php');
  exit;
}

$nuevo = ((int)$row['activo'] === 1) ? 0 : 1;
$up = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
$up->execute([$nuevo, $id]);

flash_set('ok', $nuevo ? "Usuario activado." : "Usuario desactivado.");
header('Location: usuarios.php');
exit;
