<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/auth.php';

start_app_session();
require_login();

require __DIR__ . '/../app/views/layout/header.php';
require __DIR__ . '/../app/views/layout/sidebar.php';
?>

<style>
  .card-soft{border-radius:14px;}
  .btn-pill{border-radius:10px;}
  .legend-badge{display:inline-flex;align-items:center;gap:8px;margin-right:10px;margin-bottom:6px;}
  .legend-dot{width:10px;height:10px;border-radius:50%;}
</style>

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
        <div class="legend-badge"><span class="legend-dot" style="background:#0d6efd;"></span> CONFIRMADA</div>
        <div class="legend-badge"><span class="legend-dot" style="background:#212529;"></span> ENTREGADA</div>
        <div class="legend-badge"><span class="legend-dot" style="background:#198754;"></span> DEVUELTA</div>
        <div class="legend-badge"><span class="legend-dot" style="background:#6c757d;"></span> BORRADOR</div>
        <div class="legend-badge"><span class="legend-dot" style="background:#dc3545;"></span> CANCELADA</div>
      </div>

      <div class="d-flex align-items-center gap-2">
        <select id="filterOnly" class="form-select" style="min-width: 260px;">
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

<script>
  const filterOnly = document.getElementById('filterOnly');
  const btnRefresh = document.getElementById('btnRefresh');

  function buildEventsUrl(info) {
    const only = filterOnly.value;
    const u = new URL('api_reservas.php', window.location.href);
    u.searchParams.set('start', info.startStr);
    u.searchParams.set('end', info.endStr);
    if (only) u.searchParams.set('only', only);
    return u.toString();
  }

  document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');

    const cal = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      height: 'auto',
      locale: 'es',
      firstDay: 1,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
      },

      // Cargar eventos desde API
      events: function(info, success, failure) {
        fetch(buildEventsUrl(info))
          .then(r => r.json())
          .then(data => success(data))
          .catch(err => failure(err));
      },

      eventClick: function(arg) {
        const id = arg.event.id;
        const estado = arg.event.extendedProps.estado;

        // Lógica de navegación
        // - CONFIRMADA/ENTREGADA: operación diaria
        // - BORRADOR/DEVUELTA: detalle
        if (estado === 'CONFIRMADA' || estado === 'ENTREGADA') {
          window.location.href = `reserva_operacion.php?id=${id}`;
        } else {
          window.location.href = `reserva_editar.php?id=${id}`;
        }
      },

      eventDidMount: function(info) {
        const p = info.event.extendedProps;
        const tip = `${p.codigo} | ${p.cliente}\nEstado: ${p.estado}\nSalida: ${p.salida}\nRetorno: ${p.retorno}\nTel: ${p.telefono}`;
        info.el.setAttribute('title', tip);
      }
    });

    cal.render();

    btnRefresh.addEventListener('click', () => cal.refetchEvents());
    filterOnly.addEventListener('change', () => cal.refetchEvents());
  });
</script>

<?php require __DIR__ . '/../app/views/layout/footer.php'; ?>
