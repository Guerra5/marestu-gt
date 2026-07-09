<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/flash.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

start_app_session();

if (is_logged_in()) {
  header('Location: index.php');
  exit;
}

$pdo = db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate($_POST['csrf'] ?? null)) {
    $error = "Token inválido. Recargá la página.";
  } else {
    // 👇 Ajustá estos names si tu sistema usa otros
    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $pass = (string)($_POST['password'] ?? ''); // si tu input se llama "contrasena", cambiá aquí

    if ($usuario === '' || $pass === '') {
      $error = "Ingresá usuario y contraseña.";
    } else {
      // --- Lógica típica: buscar usuario activo y verificar password_hash
      $st = $pdo->prepare("SELECT id, nombre, usuario, rol, activo, password_hash
                           FROM usuarios
                           WHERE usuario = ?
                           LIMIT 1");
      $st->execute([$usuario]);
      $u = $st->fetch();

      if (!$u || (int)$u['activo'] !== 1) {
        $error = "Usuario o contraseña inválidos.";
      } elseif (!password_verify($pass, (string)$u['password_hash'])) {
        $error = "Usuario o contraseña inválidos.";
      } else {
        // login ok
        $_SESSION['user'] = [
          'id' => (int)$u['id'],
          'nombre' => (string)$u['nombre'],
          'usuario' => (string)$u['usuario'],
          'rol' => (string)$u['rol'],
        ];
        header('Location: index.php');
        exit;
      }
    }
  }
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login · MARESTU</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
  <div class="login-bg"></div>

  <div class="login-wrap">
    <div class="login-card">

      <!-- Lado izquierdo (branding) -->
      <div class="login-left">
        <div class="login-brand">
          <div class="login-logo">
            <i class="bi bi-stars fs-4"></i>
          </div>
          <div>
            <div class="fw-bold">MARESTU</div>
            <div style="color: rgba(255,255,255,.70); font-size: 14px;">Sistema de Alquifiestas</div>
          </div>
        </div>

        <div class="login-title">Controlá reservas e inventario sin enredos.</div>
        <div class="login-sub">
          Verificá disponibilidad por fechas, registrá entregas/devoluciones y mantené historial (kardex).
        </div>

        <ul class="login-bullets">
          <li>Disponibilidad por fechas (anti sobre-reservas)</li>
          <li>Operación diaria: entrega/devolución con checklist</li>
          <li>Dashboard + calendario para planificar</li>
        </ul>
      </div>

      <!-- Lado derecho (form) -->
      <div class="login-right">
        <h4>Iniciar sesión</h4>
        <div class="hint">Ingresá con tu usuario y contraseña.</div>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off" class="mt-2">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

          <div class="mb-3">
            <label class="form-label">Usuario</label>
            <div class="input-icon">
              <i class="bi bi-person"></i>
              <input name="usuario" class="form-control form-control-lg" placeholder="ej. cvargas" required>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label">Contraseña</label>
            <div class="input-icon">
              <i class="bi bi-lock"></i>
              <input id="password" name="password" type="password" class="form-control form-control-lg" placeholder="••••••••" required>
            </div>
          </div>

          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="showPass">
              <label class="form-check-label" for="showPass">Mostrar</label>
            </div>
            <span class="small text-muted">v1.0</span>
          </div>

          <button class="btn btn-primary btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i> Entrar
          </button>

          <!-- <div class="login-footer">
            Si es primera vez: abrí <b>instalar.php</b> para crear el admin y luego borralo.
          </div> -->
        </form>
      </div>

    </div>
  </div>

<script>
  const cb = document.getElementById('showPass');
  const pw = document.getElementById('password');
  cb?.addEventListener('change', () => {
    pw.type = cb.checked ? 'text' : 'password';
  });
</script>
</body>
</html>
