<div
  class="modal fade"
  id="modalEntregaAdicional"
  tabindex="-1"
  aria-labelledby="tituloModalEntregaAdicional"
  aria-hidden="true"
>
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div
      class="modal-content"
      data-style="reserva_operacion-1"
    >
      <div class="modal-header">
        <h5
          class="modal-title"
          id="tituloModalEntregaAdicional"
        >
          Agregar al pedido (solo ADMIN)
        </h5>
        <button
          type="button"
          class="btn-close"
          data-bs-dismiss="modal"
          aria-label="Cerrar"
        ></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input
            type="hidden"
            name="csrf"
            value="<?= htmlspecialchars(csrf_token()) ?>"
          >
          <input
            type="hidden"
            name="action"
            value="entrega_adicional"
          >
          <div class="alert alert-info small">
            Esto agrega un artículo o servicio extra a la reserva confirmada.
            Quedará <b>pendiente de entrega</b> y aparecerá en el formulario
            de Registrar entrega. No genera movimientos de kardex todavía.
          </div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">
                Tipo
              </label>
              <select
                class="form-select"
                name="tipo"
                id="tipoEntregaAdicional"
                required
              >
                <option value="INV" selected>
                  Inventario (bodega)
                </option>
                <option value="EXT">
                  Extra / servicio externo
                </option>
              </select>
            </div>
            <!-- INVENTARIO -->
            <div
              class="col-12"
              id="wrapINV"
            >
              <div class="row g-3">
                <div class="col-12 col-lg-4">
                  <label class="form-label fw-semibold">
                    Categoría
                  </label>
                  <select
                    class="form-select"
                    id="categoriaArticuloEntregaAdicional"
                  >
                    <option value="">
                      Todas las categorías
                    </option>
                    <?php
                      $categorias_modal = [];
                      foreach ($arts as $articulo_modal) {
                        $categoria_modal = trim(
                          (string)$articulo_modal['categoria']
                        );
                        if ($categoria_modal !== '') {
                          $categorias_modal[$categoria_modal] = true;
                        }
                      }
                      $categorias_modal = array_keys($categorias_modal);
                      natcasesort($categorias_modal);
                    ?>
                    <?php foreach ($categorias_modal as $categoria_modal): ?>
                      <option value="<?= htmlspecialchars($categoria_modal) ?>">
                        <?= htmlspecialchars($categoria_modal) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-lg-8">
                  <label class="form-label fw-semibold">
                    Buscar artículo
                  </label>
                  <input
                    class="form-control"
                    type="search"
                    id="buscarArticuloEntregaAdicional"
                    placeholder="Buscar por código o nombre..."
                    autocomplete="off"
                  >
                </div>
                <div class="col-12 col-lg-8">
                  <label class="form-label fw-semibold">
                    Artículo
                  </label>
                  <select
                    class="form-select"
                    name="articulo_id"
                    id="articuloEntregaAdicional"
                  >
                    <option value="">
                      -- Seleccionar --
                    </option>
                    <?php foreach ($arts as $a): ?>
                      <option
                        value="<?= (int)$a['id'] ?>"
                        data-categoria="<?= htmlspecialchars((string)$a['categoria']) ?>"
                        data-codigo="<?= htmlspecialchars((string)$a['codigo']) ?>"
                        data-nombre="<?= htmlspecialchars((string)$a['nombre']) ?>"
                      >
                        <?= htmlspecialchars((string)$a['categoria']) ?>
                        ·
                        <?= htmlspecialchars((string)$a['nombre']) ?>
                        (<?= htmlspecialchars((string)$a['codigo']) ?>)
                        [Stock activo:
                        <?= (int)$a['cantidad_activa'] ?>]
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-lg-4">
                  <label class="form-label fw-semibold">
                    Cantidad a agregar
                  </label>
                  <input
                    class="form-control"
                    type="number"
                    min="1"
                    step="1"
                    inputmode="numeric"
                    name="cantidad_inv"
                    id="cantidadInvAdicional"
                    value="1"
                  >
                </div>
                <div class="col-12">
                  <div class="small text-muted">
                    * Aumenta la cantidad solicitada y queda pendiente.
                    La SALIDA en kardex se genera únicamente al registrar la entrega.
                  </div>
                </div>
              </div>
            </div>
            <!-- EXTRA EXTERNO -->
            <div
              class="col-12 d-none"
              id="wrapEXT"
            >
              <div class="row g-3">
                <div class="col-12 col-lg-6">
                  <label class="form-label fw-semibold">
                    Descripción
                  </label>
                  <input
                    class="form-control"
                    name="descripcion"
                    id="descripcionExtraAdicional"
                    placeholder="Ej: Manteles prestados, transporte, meseros..."
                  >
                </div>
                <div class="col-12 col-lg-6">
                  <label class="form-label fw-semibold">
                    Proveedor (opcional)
                  </label>
                  <input
                    class="form-control"
                    name="proveedor"
                    placeholder="Ej: Alquifiesta X"
                  >
                </div>
                <div class="col-6 col-lg-3">
                  <label class="form-label fw-semibold">
                    Cantidad
                  </label>
                  <input
                    class="form-control"
                    type="number"
                    min="1"
                    step="1"
                    inputmode="numeric"
                    name="cantidad_ext"
                    id="cantidadExtAdicional"
                    value="1"
                  >
                </div>
                <div class="col-6 col-lg-3">
                  <label class="form-label fw-semibold">
                    Precio (Q)
                  </label>
                  <input
                    class="form-control"
                    type="number"
                    step="0.01"
                    min="0"
                    name="precio_unitario"
                    value="0.00"
                  >
                </div>
                <div class="col-12">
                  <div class="small text-muted">
                    * Se crea un EXTRA pendiente de entrega.
                    No toca el kardex de inventario.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-outline-secondary btn-pill"
            data-bs-dismiss="modal"
          >
            Cancelar
          </button>
          <button
            class="btn btn-primary btn-pill"
            type="submit"
          >
            Agregar al pedido
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
