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
  <link rel="stylesheet" href="assets/css/layout.css">
</head>

<body class="bg-light app-wrap">
<div class="container-fluid p-0">
  <div class="row g-0">
