<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/../layout/sidebar.php'; ?>

<link rel="stylesheet" href="assets/css/pages/calendario.css">
<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <div class="text-muted small">Operación</div>
    <h3 class="mb-1">Calendario de reservas</h3>
    <div class="text-muted">Vista rápida para disponibilidad, salidas y retornos.</div>
  </div>
  <div class="d-flex gap-2">
    <a href="reservas.php" class="btn btn-outline-secondary btn-pill">Reservas</a>
  </div>
</div>
<div class="card card-soft shadow-sm mb-3">
  <div class="card-body p-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <div class="legend-badge"><span class="legend-dot" data-style="calendario-1"></span> CONFIRMADA</div>
        <div class="legend-badge"><span class="legend-dot" data-style="calendario-2"></span> ENTREGADA</div>
        <div class="legend-badge"><span class="legend-dot" data-style="calendario-3"></span> DEVUELTA</div>
        <div class="legend-badge"><span class="legend-dot" data-style="calendario-4"></span> BORRADOR</div>
        <div class="legend-badge"><span class="legend-dot" data-style="calendario-5"></span> CANCELADA</div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <select id="filterOnly" class="form-select" data-style="calendario-6">
          <option value="">Mostrar todo (excepto canceladas)</option>
          <option value="open">Solo CONFIRMADAS + ENTREGADAS</option>
        </select>
        <button id="btnRefresh" class="btn btn-outline-primary btn-pill">Refrescar</button>
      </div>
    </div>
  </div>
</div>
<div class="card card-soft shadow-sm">
  <div class="card-body p-3">
    <div id="calendar"></div>
  </div>
</div>
<!-- FullCalendar -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="assets/js/pages/calendario.js"></script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
