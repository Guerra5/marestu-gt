(() => {
'use strict';
const pageData = JSON.parse(document.getElementById('reservas-data').textContent);

(function () {
  'use strict';

  const fechaSalida = document.getElementById('fechaSalida');
  const fechaEvento = document.getElementById('fechaEvento');
  const fechaRetorno = document.getElementById('fechaRetorno');
  const form = document.getElementById('formCrearReserva');

  if (!fechaSalida || !fechaEvento || !fechaRetorno || !form) {
    return;
  }

  function actualizarLimites() {
    const salida = fechaSalida.value;
    const evento = fechaEvento.value;

    fechaEvento.min = salida || pageData.value0;

    if (salida && fechaEvento.value && fechaEvento.value < salida) {
      fechaEvento.value = salida;
    }

    const minimoRetorno = fechaEvento.value || salida || pageData.value1;
    fechaRetorno.min = minimoRetorno;

    if (
      minimoRetorno &&
      fechaRetorno.value &&
      fechaRetorno.value < minimoRetorno
    ) {
      fechaRetorno.value = minimoRetorno;
    }

    if (evento && fechaRetorno.value && fechaRetorno.value < evento) {
      fechaRetorno.value = evento;
    }
  }

  fechaSalida.addEventListener('change', actualizarLimites);
  fechaEvento.addEventListener('change', actualizarLimites);
  fechaRetorno.addEventListener('change', actualizarLimites);

  form.addEventListener('submit', function (event) {
    if (
      fechaSalida.value === '' ||
      fechaEvento.value === '' ||
      fechaRetorno.value === ''
    ) {
      event.preventDefault();
      alert('Las tres fechas son obligatorias.');
      return;
    }

    if (fechaEvento.value < fechaSalida.value) {
      event.preventDefault();
      alert('La fecha del evento no puede ser anterior a la fecha de salida.');
      fechaEvento.focus();
      return;
    }

    if (fechaRetorno.value < fechaEvento.value) {
      event.preventDefault();
      alert('La fecha de retorno no puede ser anterior a la fecha del evento.');
      fechaRetorno.focus();
    }
  });

  actualizarLimites();
})();
})();
