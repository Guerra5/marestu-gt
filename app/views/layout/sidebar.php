<?php
declare(strict_types=1);

$u = current_user();
$current = basename($_SERVER['PHP_SELF']);

$is_active = static fn (string $file, string $current): string => $file === $current ? 'active' : '';

ob_start();
?>
  <div class="d-flex align-items-center justify-content-between mb-3">
    <strong class="text-white">MARESTU</strong>
    <span class="badge rounded-pill text-bg-light">
      <?= htmlspecialchars($u['rol'] ?? '') ?>
    </span>
  </div>

  <div class="small mb-3" data-style="layout-1">
    <div class="fw-semibold text-white"><?= htmlspecialchars($u['nombre'] ?? '') ?></div>
    <div data-style="layout-2">@<?= htmlspecialchars($u['usuario'] ?? '') ?></div>
  </div>

  <div class="list-group list-group-flush">
    <a href="index.php" class="list-group-item list-group-item-action <?= $is_active('index.php',$current) ?>">
      <i class="bi bi-speedometer2 me-2"></i> Inicio
    </a>

    <?php if (is_admin()): ?>
      <a href="usuarios.php" class="list-group-item list-group-item-action <?= $is_active('usuarios.php',$current) ?>">
        <i class="bi bi-people me-2"></i> Usuarios
      </a>
    <?php endif; ?>

    <a href="categorias.php" class="list-group-item list-group-item-action <?= $is_active('categorias.php',$current) ?>">
      <i class="bi bi-tags me-2"></i> Categorías
    </a>

    <a href="articulos.php" class="list-group-item list-group-item-action <?= $is_active('articulos.php',$current) ?>">
      <i class="bi bi-box-seam me-2"></i> Artículos
    </a>

    <a href="kardex.php" class="list-group-item list-group-item-action <?= $is_active('kardex.php',$current) ?>">
      <i class="bi bi-journal-text me-2"></i> Kardex
    </a>

    <a href="clientes.php" class="list-group-item list-group-item-action <?= $is_active('clientes.php',$current) ?>">
      <i class="bi bi-person-badge me-2"></i> Clientes
    </a>

    <!-- ✅ Reservas con badge de alertas (hoy/mañana) -->
    <a href="reservas.php"
       class="list-group-item list-group-item-action d-flex align-items-center justify-content-between <?= $is_active('reservas.php',$current) ?>">
      <span><i class="bi bi-calendar-check me-2"></i> Reservas</span>
      <?php if ($alertas > 0): ?>
        <span class="badge rounded-pill text-bg-danger"><?= $alertas ?></span>
      <?php endif; ?>
    </a>

    <a href="calendario.php" class="list-group-item list-group-item-action <?= $is_active('calendario.php',$current) ?>">
      <i class="bi bi-calendar3 me-2"></i> Calendario
    </a>
  </div>

  <div class="mt-4 pt-3 border-top" data-style="layout-3">
    <a href="logout.php" class="btn btn-outline-light w-100 btn-pill">
      <i class="bi bi-box-arrow-right me-2"></i> Salir
    </a>
  </div>
<?php
$menuHtml = ob_get_clean();
?>

<!-- ✅ TOPBAR MÓVIL (solo se ve < 993px por CSS en header.php) -->
<div class="mobile-topbar text-white px-3 py-2 d-flex align-items-center justify-content-between">
  <button class="btn btn-outline-light btn-sm"
          type="button"
          data-bs-toggle="offcanvas"
          data-bs-target="#offcanvasMenu"
          aria-controls="offcanvasMenu">
    <i class="bi bi-list"></i> Menú
  </button>

  <div class="fw-semibold">MARESTU</div>

  <span class="badge rounded-pill text-bg-light">
    <?= htmlspecialchars($u['rol'] ?? '') ?>
  </span>
</div>

<!-- Sidebar DESKTOP (se oculta en móvil por .sidebar-fixed en header.php) -->
<nav class="col-12 col-md-3 col-lg-2 app-sidebar p-3 sidebar-fixed">
  <?= $menuHtml ?>
</nav>

<!-- ✅ OFFCANVAS MÓVIL -->
<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="offcanvasMenu" aria-labelledby="offcanvasMenuLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasMenuLabel">MARESTU</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body p-3">
    <?= $menuHtml ?>
  </div>
</div>

<!-- Main -->
<main class="col-12 col-md-9 col-lg-10 p-4 page-animate main-col page-pad">
