(() => {
'use strict';
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const modalEditar = document.getElementById('modalEditarPedido');

  if (modalEditar && modalEditar.parentElement !== document.body) {
    document.body.appendChild(modalEditar);
  }

  document.querySelectorAll('.btnEditarPedido').forEach(function (button) {
    button.addEventListener('click', function () {
      const tipo = button.dataset.tipo;
      const id = button.dataset.id;
      const detalle = button.dataset.detalle || '';
      const cantidad = parseInt(button.dataset.cantidad || '1', 10);
      const entregado = parseInt(button.dataset.entregado || '0', 10);

      const action = document.getElementById('editarPedidoAction');
      const detalleId = document.getElementById('editarDetalleId');
      const extraId = document.getElementById('editarExtraId');
      const nombre = document.getElementById('editarPedidoDetalle');
      const input = document.getElementById('editarNuevaCantidad');
      const ayuda = document.getElementById('editarPedidoAyuda');

      if (!action || !detalleId || !extraId || !nombre || !input || !ayuda) {
        return;
      }

      action.value = tipo === 'INV'
        ? 'editar_item_pedido'
        : 'editar_extra_pedido';

      detalleId.value = tipo === 'INV' ? id : '';
      extraId.value = tipo === 'EXT' ? id : '';
      nombre.textContent = detalle;
      input.value = String(cantidad);
      input.min = String(Math.max(entregado, 1));
      ayuda.textContent = entregado > 0
        ? 'Mínimo permitido: ' + entregado + ' porque esa cantidad ya fue entregada.'
        : 'Podés reducir o aumentar la cantidad. Para eliminar por completo usá Quitar.';
    });
  });

  const modal = document.getElementById(
    'modalEntregaAdicional'
  );

  /*
   * CORRECCIÓN DEL BLOQUEO:
   *
   * El modal se genera inicialmente dentro de <main class="page-animate">.
   * Esa clase utiliza transform durante la animación y crea un contexto
   * de apilamiento propio.
   *
   * Bootstrap agrega el fondo oscuro directamente dentro de <body>.
   * En algunos navegadores, el backdrop termina encima del modal y
   * bloquea todos los clics.
   *
   * Movemos el modal directamente a <body> antes de utilizarlo.
   */
  if (modal && modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }

  const sel = document.getElementById(
    'tipoEntregaAdicional'
  );

  const inv = document.getElementById(
    'wrapINV'
  );

  const ext = document.getElementById(
    'wrapEXT'
  );

  const articuloInv = document.getElementById(
    'articuloEntregaAdicional'
  );

  const buscarArticuloInv = document.getElementById(
    'buscarArticuloEntregaAdicional'
  );

  const categoriaArticuloInv = document.getElementById(
    'categoriaArticuloEntregaAdicional'
  );

  const opcionesArticuloInv = articuloInv
    ? Array.from(articuloInv.options).map(function (option) {
        return {
          value: option.value,
          text: option.textContent || '',
          html: option.innerHTML,
          categoria: option.dataset.categoria || '',
          codigo: option.dataset.codigo || '',
          nombre: option.dataset.nombre || ''
        };
      })
    : [];

  const cantidadInv = document.getElementById(
    'cantidadInvAdicional'
  );

  const descripcionExt = document.getElementById(
    'descripcionExtraAdicional'
  );

  const cantidadExt = document.getElementById(
    'cantidadExtAdicional'
  );

  if (!modal || !sel || !inv || !ext) {
    return;
  }

  function toggle() {
    const esExtra = sel.value === 'EXT';

    if (esExtra) {
      inv.classList.add('d-none');
      ext.classList.remove('d-none');

      if (articuloInv) {
        articuloInv.required = false;
      }

      if (cantidadInv) {
        cantidadInv.required = false;
      }

      if (descripcionExt) {
        descripcionExt.required = true;
      }

      if (cantidadExt) {
        cantidadExt.required = true;
      }
    } else {
      ext.classList.add('d-none');
      inv.classList.remove('d-none');

      if (articuloInv) {
        articuloInv.required = true;
      }

      if (cantidadInv) {
        cantidadInv.required = true;
      }

      if (descripcionExt) {
        descripcionExt.required = false;
      }

      if (cantidadExt) {
        cantidadExt.required = false;
      }
    }
  }

  sel.addEventListener(
    'change',
    toggle
  );

  function normalizarTexto(texto) {
    return texto
      .toLocaleLowerCase('es')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim();
  }

  function filtrarArticulos() {
    if (!articuloInv) {
      return;
    }

    const valorSeleccionado = articuloInv.value;
    const termino = buscarArticuloInv
      ? normalizarTexto(buscarArticuloInv.value)
      : '';
    const categoriaSeleccionada = categoriaArticuloInv
      ? normalizarTexto(categoriaArticuloInv.value)
      : '';

    articuloInv.innerHTML = '';

    opcionesArticuloInv.forEach(function (opcion) {
      const esPlaceholder = opcion.value === '';
      const categoria = normalizarTexto(opcion.categoria);
      const textoBusqueda = normalizarTexto(
        opcion.codigo + ' ' + opcion.nombre + ' ' + opcion.categoria
      );

      const coincideCategoria =
        categoriaSeleccionada === '' ||
        categoria === categoriaSeleccionada;

      const coincideBusqueda =
        termino === '' ||
        textoBusqueda.includes(termino);

      if (
        esPlaceholder ||
        (coincideCategoria && coincideBusqueda)
      ) {
        const nuevaOpcion = document.createElement('option');
        nuevaOpcion.value = opcion.value;
        nuevaOpcion.innerHTML = opcion.html;

        if (opcion.categoria !== '') {
          nuevaOpcion.dataset.categoria = opcion.categoria;
        }

        if (opcion.codigo !== '') {
          nuevaOpcion.dataset.codigo = opcion.codigo;
        }

        if (opcion.nombre !== '') {
          nuevaOpcion.dataset.nombre = opcion.nombre;
        }

        articuloInv.appendChild(nuevaOpcion);
      }
    });

    const seleccionExiste = Array.from(articuloInv.options).some(
      function (option) {
        return option.value === valorSeleccionado;
      }
    );

    articuloInv.value = seleccionExiste ? valorSeleccionado : '';
  }

  if (buscarArticuloInv) {
    buscarArticuloInv.addEventListener('input', filtrarArticulos);
  }

  if (categoriaArticuloInv) {
    categoriaArticuloInv.addEventListener('change', filtrarArticulos);
  }

  modal.addEventListener('shown.bs.modal', function () {
    if (sel.value === 'INV' && buscarArticuloInv) {
      buscarArticuloInv.focus();
    }
  });

  toggle();

  /*
   * Limpieza preventiva si Brave conserva accidentalmente
   * un backdrop después de cerrar el modal.
   */
  if (modalEditar) {
    modalEditar.addEventListener('hidden.bs.modal', function () {
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
      document.body.style.removeProperty('overflow');
      document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
        backdrop.remove();
      });
    });
  }

  modal.addEventListener(
    'hidden.bs.modal',
    function () {
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
      document.body.style.removeProperty('overflow');

      document
        .querySelectorAll('.modal-backdrop')
        .forEach(function (backdrop) {
          backdrop.remove();
        });
    }
  );
});
})();
