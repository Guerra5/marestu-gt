(() => {
'use strict';
document.addEventListener('DOMContentLoaded', function () {
  const categoria = document.getElementById('categoriaKardex');
  const articulo = document.getElementById('articuloKardex');

  if (!categoria || !articulo) return;

  const opciones = Array.from(articulo.options).slice(1);

  function filtrarArticulos() {
    const categoriaId = categoria.value;
    const seleccionado = articulo.value;
    let sigueVisible = seleccionado === '0';

    opciones.forEach(function (opcion) {
      const visible = categoriaId === '0' || opcion.dataset.categoria === categoriaId;
      opcion.hidden = !visible;

      if (opcion.value === seleccionado && visible) {
        sigueVisible = true;
      }
    });

    if (!sigueVisible) {
      articulo.value = '0';
    }
  }

  categoria.addEventListener('change', filtrarArticulos);
  filtrarArticulos();
});
})();
