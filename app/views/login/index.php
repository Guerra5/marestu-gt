<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login · MARESTU</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/pages/login.css">
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
            <div data-style="login-1">Sistema de Alquifiestas</div>
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
<script src="assets/js/pages/login.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
