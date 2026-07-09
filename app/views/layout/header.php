<?php
declare(strict_types=1);
$cfg = require __DIR__ . '/../../config/app.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($cfg['app_name']) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/app.css">

  <!-- ✅ FIX RESPONSIVE (solo layout, no afecta módulos) -->
  <style>
    /* Evita overflow raro en móviles con sidebar + tables */
    html, body { height: 100%; }
    .app-wrap { min-height: 100vh; }

    /* En móviles, el contenido no debe ser empujado por el sidebar */
    @media (max-width: 992px) {
      .sidebar-fixed { display: none !important; } /* el sidebar desktop se oculta */
      .main-col { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
      .page-pad { padding: 12px !important; }
    }

    /* En desktop, sidebar visible */
    @media (min-width: 993px) {
      .mobile-topbar { display: none !important; }
    }

    /* Topbar sticky en móvil */
    .mobile-topbar {
      position: sticky;
      top: 0;
      z-index: 1030;
      background: #0b1220;
      border-bottom: 1px solid rgba(255,255,255,.08);
    }
  </style>
</head>

<body class="bg-light app-wrap">
<div class="container-fluid p-0">
  <div class="row g-0">
