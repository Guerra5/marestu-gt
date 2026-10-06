    <!-- ===================================================
         ARTÍCULOS
    ==================================================== -->
    <div class="card card-soft shadow-sm mb-3">
      <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
          <h5 class="mb-0">
            Artículos en la reserva
          </h5>
          <span class="small text-muted">
            * Solo editable en BORRADOR
          </span>
        </div>
        <?php if (can('reservas.gestionar') && $res['estado'] === 'BORRADOR'): ?>
          <form
            method="post"
            class="row g-2 mb-3"
            id="formAgregarArticulo"
          >
            <input
              type="hidden"
              name="csrf"
              value="<?= htmlspecialchars(csrf_token()) ?>"
            >
            <input
              type="hidden"
              name="action"
              value="add_item"
            >
            <!-- Categoría -->
            <div class="col-12 col-lg-3">
              <label class="form-label">
                Categoría
              </label>
              <select
                class="form-select"
                id="filtroCategoriaReserva"
              >
                <option value="0">
                  Todas las categorías
                </option>
                <?php foreach ($categorias as $categoria_id => $categoria_nombre): ?>
                  <option value="<?= (int)$categoria_id ?>">
                    <?= htmlspecialchars($categoria_nombre) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Buscar -->
            <div class="col-12 col-lg-3">
              <label class="form-label">
                Buscar artículo
              </label>
              <input
                type="search"
                class="form-control"
                id="buscarArticuloReserva"
                placeholder="Código o nombre..."
                autocomplete="off"
              >
            </div>
            <!-- Artículo -->
            <div class="col-12 col-lg-4">
              <label class="form-label">
                Artículo
              </label>
              <select
                class="form-select"
                name="articulo_id"
                id="selectArticuloReserva"
                required
              >
                <option value="">
                  -- Seleccionar --
                </option>
                <?php foreach ($arts as $a): ?>
                  <option
                    value="<?= (int)$a['id'] ?>"
                    data-categoria="<?= (int)$a['categoria_id'] ?>"
                  >
                    <?= htmlspecialchars((string)$a['categoria']) ?>
                    ·
                    <?= htmlspecialchars((string)$a['nombre']) ?>
                    (<?= htmlspecialchars((string)$a['codigo']) ?>)
                    [Stock: <?= (int)$a['cantidad_activa'] ?>]
                    [Q <?= number_format((float)$a['precio_unitario'], 2) ?>]
                  </option>
                <?php endforeach; ?>
              </select>
              <div
                class="small text-muted mt-1 article-results"
                id="contadorArticulosReserva"
              ></div>
            </div>
            <!-- Cantidad -->
            <div class="col-6 col-lg-1">
              <label class="form-label">
                Cantidad
              </label>
              <input
                type="number"
                min="1"
                step="1"
                inputmode="numeric"
                name="cantidad"
                class="form-control"
                required
              >
            </div>
            <!-- Botón -->
            <div class="col-6 col-lg-1 d-flex align-items-end">
              <button
                class="btn btn-primary btn-pill w-100"
                title="Agregar o actualizar artículo"
              >
                Agregar
              </button>
            </div>
            <div class="col-12">
              <div class="small text-muted">
                * Se valida disponibilidad real para el rango
                <?= htmlspecialchars((string)$res['fecha_salida']) ?>
                →
                <?= htmlspecialchars((string)$res['fecha_retorno']) ?>.
              </div>
            </div>
          </form>
        <?php endif; ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Código</th>
                <th>Artículo</th>
                <th>Categoría</th>
                <th>Cant.</th>
                <th>Precio</th>
                <th>Subtotal</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$items): ?>
                <tr>
                  <td
                    colspan="8"
                    class="text-center py-4 text-muted"
                  >
                    Sin artículos.
                  </td>
                </tr>
              <?php endif; ?>
              <?php $n = 0; ?>
              <?php foreach ($items as $it): ?>
                <?php
                  $n++;
                  $sub = (
                    (float)$it['precio_unitario']
                  ) * (
                    (int)$it['cantidad']
                  );
                ?>
                <tr>
                  <td>
                    <?= $n ?>
                  </td>
                  <td class="mono">
                    <?= htmlspecialchars((string)$it['codigo']) ?>
                  </td>
                  <td>
                    <?= htmlspecialchars((string)$it['nombre']) ?>
                  </td>
                  <td>
                    <?= htmlspecialchars((string)$it['categoria']) ?>
                  </td>
                  <td>
                    <span class="badge text-bg-dark">
                      <?= (int)$it['cantidad'] ?>
                    </span>
                  </td>
                  <td>
                    Q <?= number_format(
                      (float)$it['precio_unitario'],
                      2
                    ) ?>
                  </td>
                  <td class="fw-semibold">
                    Q <?= number_format($sub, 2) ?>
                  </td>
                  <td class="text-end">
                    <?php if (can('reservas.gestionar') && $res['estado'] === 'BORRADOR'): ?>
                      <form
                        method="post"
                        class="m-0"
                        data-confirm="¿Quitar artículo?"
                      >
                        <input
                          type="hidden"
                          name="csrf"
                          value="<?= htmlspecialchars(csrf_token()) ?>"
                        >
                        <input
                          type="hidden"
                          name="action"
                          value="remove_item"
                        >
                        <input
                          type="hidden"
                          name="det_id"
                          value="<?= (int)$it['id'] ?>"
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
