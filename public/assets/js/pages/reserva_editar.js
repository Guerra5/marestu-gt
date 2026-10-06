(() => {
'use strict';
(function () {

  'use strict';


  const categoria = document.getElementById(

    'filtroCategoriaReserva'

  );


  const buscador = document.getElementById(

    'buscarArticuloReserva'

  );


  const selectArticulo = document.getElementById(

    'selectArticuloReserva'

  );


  const contador = document.getElementById(

    'contadorArticulosReserva'

  );


  if (

    !categoria ||

    !buscador ||

    !selectArticulo ||

    !contador

  ) {

    return;

  }


  /*

   * Guardamos todos los artículos en memoria.

   * Después reconstruimos el select según categoría y búsqueda.

   */

  const articulos = Array.from(

    selectArticulo.querySelectorAll(

      'option[data-categoria]'

    )

  ).map(function (opcion) {

    return {

      value: opcion.value,

      categoria: opcion.dataset.categoria || '',

      texto: opcion.textContent.trim()

    };

  });


  function normalizarTexto(texto) {

    return texto

      .toLocaleLowerCase('es')

      .normalize('NFD')

      .replace(/[\u0300-\u036f]/g, '')

      .trim();

  }


  function actualizarArticulos() {

    const categoriaSeleccionada = categoria.value;

    const textoBuscado = normalizarTexto(buscador.value);


    const articuloSeleccionado = selectArticulo.value;


    const resultados = articulos.filter(function (articulo) {

      const coincideCategoria =

        categoriaSeleccionada === '0' ||

        articulo.categoria === categoriaSeleccionada;


      const coincideBusqueda =

        textoBuscado === '' ||

        normalizarTexto(articulo.texto).includes(textoBuscado);


      return coincideCategoria && coincideBusqueda;

    });


    selectArticulo.innerHTML = '';


    const placeholder = document.createElement('option');

    placeholder.value = '';


    placeholder.textContent = resultados.length > 0

      ? '-- Seleccionar --'

      : '-- Sin artículos encontrados --';


    selectArticulo.appendChild(placeholder);


    resultados.forEach(function (articulo) {

      const opcion = document.createElement('option');


      opcion.value = articulo.value;

      opcion.textContent = articulo.texto;

      opcion.dataset.categoria = articulo.categoria;


      if (articulo.value === articuloSeleccionado) {

        opcion.selected = true;

      }


      selectArticulo.appendChild(opcion);

    });


    const seleccionConservada = resultados.some(

      function (articulo) {

        return articulo.value === articuloSeleccionado;

      }

    );


    if (!seleccionConservada) {

      selectArticulo.value = '';

    }


    contador.textContent =

      resultados.length === 1

        ? '1 artículo disponible'

        : resultados.length + ' artículos disponibles';

  }


  categoria.addEventListener(

    'change',

    actualizarArticulos

  );


  buscador.addEventListener(

    'input',

    actualizarArticulos

  );


  actualizarArticulos();

})();
})();
