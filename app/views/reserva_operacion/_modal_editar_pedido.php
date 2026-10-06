<div
  class="modal fade"
  id="modalEditarPedido"
  tabindex="-1"
  aria-labelledby="tituloModalEditarPedido"
  aria-hidden="true"
>
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" data-style="reserva_operacion-1">
      <div class="modal-header">
        <h5 class="modal-title" id="tituloModalEditarPedido">
          Editar cantidad
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="post" id="formEditarPedido">
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" id="editarPedidoAction">
          <input type="hidden" name="detalle_id" id="editarDetalleId">
          <input type="hidden" name="extra_id" id="editarExtraId">
          <div class="mb-3">
            <div class="text-muted small">Artículo / extra</div>
            <div class="fw-semibold" id="editarPedidoDetalle"></div>
          </div>
          <label class="form-label fw-semibold" for="editarNuevaCantidad">
            Nueva cantidad
          </label>
          <input
            class="form-control"
            type="number"
            min="1"
            step="1"
            inputmode="numeric"
            name="nueva_cantidad"
            id="editarNuevaCantidad"
            required
          >
          <div class="form-text" id="editarPedidoAyuda"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            Cancelar
          </button>
          <button type="submit" class="btn btn-primary">
            Guardar cambio
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
