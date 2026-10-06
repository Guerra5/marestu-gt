<div class="col-12 col-lg-8">
<?php require __DIR__ . '/_articulos.php'; ?>
    <!-- ===================================================
         EXTRAS
    ==================================================== -->
    <div class="card card-soft shadow-sm">
      <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-2">
          <h5 class="mb-0">
            Extras / Servicios externos
          </h5>
          <span class="small text-muted">
            * Solo editable en BORRADOR
          </span>
        </div>
        <?php if (can('reservas.gestionar') && $res['estado'] === 'BORRADOR'): ?>
          <form method="post" class="row g-2 mb-3">
            <input
              type="hidden"
              name="csrf"
              value="<?= htmlspecialchars(csrf_token()) ?>"
            >
            <input
              type="hidden"
              name="action"
              value="add_extra"
            >
            <div class="col-12 col-lg-6">
              <label class="form-label">
                Descripción
              </label>
              <input
                class="form-control"
                name="descripcion"
                required
                placeholder="Ej: Mantelería prestada, Inflable, Sonido..."
              >
            </div>
            <div class="col-12 col-lg-3">
              <label class="form-label">
                Proveedor (opcional)
              </label>
              <input
                class="form-control"
                name="proveedor"
                placeholder="Ej: Alquifiestas X"
              >
            </div>
            <div class="col-6 col-lg-1">
              <label class="form-label">
                Cant.
              </label>
              <input
                type="number"
                min="1"
                step="1"
                inputmode="numeric"
                class="form-control"
                name="cantidad"
                value="1"
                required
              >
            </div>
            <div class="col-6 col-lg-2">
              <label class="form-label">
                Precio (Q)
              </label>
              <input
                type="number"
                min="0"
                step="0.01"
                class="form-control"
                name="precio_unitario"
                value="0.00"
                required
              >
            </div>
            <div class="col-12">
              <button class="btn btn-outline-primary btn-pill">
                Agregar extra
              </button>
            </div>
            <div class="col-12">
              <div class="small text-muted">
                * Los extras NO afectan inventario ni kardex, pero sí entran en la cotización.
              </div>
            </div>
          </form>
        <?php endif; ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Descripción</th>
                <th>Proveedor</th>
                <th>Cant.</th>
                <th>Precio</th>
                <th>Subtotal</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$extras): ?>
                <tr>
                  <td
                    colspan="7"
                    class="text-center py-4 text-muted"
                  >
                    Sin extras.
                  </td>
                </tr>
              <?php endif; ?>
              <?php $n = 0; ?>
              <?php foreach ($extras as $ex): ?>
                <?php $n++; ?>
                <tr>
                  <td>
                    <?= $n ?>
                  </td>
                  <td class="fw-semibold">
                    <?= htmlspecialchars((string)$ex['descripcion']) ?>
                  </td>
                  <td class="text-muted">
                    <?= htmlspecialchars(
                      (string)($ex['proveedor'] ?? '')
                    ) ?>
                  </td>
                  <td>
                    <span class="badge text-bg-dark">
                      <?= (int)$ex['cantidad'] ?>
                    </span>
                  </td>
                  <td>
                    Q <?= number_format(
                      (float)$ex['precio_unitario'],
                      2
                    ) ?>
                  </td>
                  <td class="fw-semibold">
                    Q <?= number_format(
                      (float)$ex['subtotal'],
                      2
                    ) ?>
                  </td>
                  <td class="text-end">
                    <?php if (can('reservas.gestionar') && $res['estado'] === 'BORRADOR'): ?>
                      <form
                        method="post"
                        class="m-0"
                        data-confirm="¿Quitar extra?"
                      >
                        <input
                          type="hidden"
                          name="csrf"
                          value="<?= htmlspecialchars(csrf_token()) ?>"
                        >
                        <input
                          type="hidden"
                          name="action"
                          value="remove_extra"
                        >
                        <input
                          type="hidden"
                          name="extra_id"
                          value="<?= (int)$ex['id'] ?>"
                        >
                        <button class="btn btn-sm btn-outline-danger btn-pill">
                          Quitar
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted small">
                        Bloqueado
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
