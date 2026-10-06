(() => {
'use strict';
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
})();
