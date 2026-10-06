<div class="col-12 col-lg-4">
    <?php if ($estado === 'CONFIRMADA'): ?>
      <div class="card card-soft shadow-sm mb-3">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">
              Registrar entrega
            </h5>
            <?php if (is_admin()): ?>
              <button
                type="button"
                class="btn btn-sm btn-outline-primary btn-pill"
                data-bs-toggle="modal"
                data-bs-target="#modalEntregaAdicional"
              >
                + Agregar al pedido
              </button>
            <?php endif; ?>
          </div>
          <?php require __DIR__ . '/_form_entrega.php'; ?>
          <div class="small text-muted mt-2">
            * Cuando se entregue TODO, cambia automáticamente
            a <b>ENTREGADA</b>.
          </div>
        </div>
      </div>
    <?php elseif ($estado === 'ENTREGADA'): ?>
      <div class="card card-soft shadow-sm">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">
              Registrar devolución
            </h5>
            <?php if (is_admin()): ?>
              <button
                type="button"
                class="btn btn-sm btn-outline-primary btn-pill"
                data-bs-toggle="modal"
                data-bs-target="#modalEntregaAdicional"
              >
                + Agregar al pedido
              </button>
            <?php endif; ?>
          </div>
          <?php require __DIR__ . '/_form_devolucion.php'; ?>
          <div class="small text-muted mt-2">
            * Inventario: Devuelto genera <b>DEVOLUCION</b> en kardex.
            Daño/Pérdida generan <b>AJUSTE</b> y bajan stock.
            <br>
            * Cuando todo esté cerrado, cambia a <b>DEVUELTA</b>.
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="card card-soft shadow-sm">
        <div class="card-body p-4">
          <h5 class="mb-2">
            Sin acciones
          </h5>
          <div class="text-muted">
            Estado actual:
            <b><?= htmlspecialchars($estado) ?></b>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
