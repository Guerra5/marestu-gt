<div class="col-12 col-lg-8">
    <div class="card card-soft shadow-sm">
      <div class="card-body p-4">
        <h5 class="mb-3">
          Checklist
        </h5>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>Tipo</th>
                <th>Detalle</th>
                <th>Cant.</th>
                <th>Entregado</th>
                <th>Cerrado</th>
                <?php if (is_admin() && in_array($estado, ['CONFIRMADA', 'ENTREGADA'], true)): ?>
                  <th class="text-end">Acciones</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <?php
                  $cerrado =
                    (int)$it['devuelto'] +
                    (int)$it['danado'] +
                    (int)$it['perdido'];
                ?>
                <tr>
                  <td>
                    <span class="badge rounded-pill text-bg-secondary">
                      Inventario
                    </span>
                  </td>
                  <td>
                    <div class="fw-semibold">
                      <span class="mono">
                        <?= htmlspecialchars((string)$it['codigo']) ?>
                      </span>
                      ·
                      <?= htmlspecialchars((string)$it['nombre']) ?>
                    </div>
                    <div class="text-muted small">
                      <?= htmlspecialchars((string)$it['categoria']) ?>
                    </div>
                  </td>
                  <td>
                    <span class="badge text-bg-dark">
                      <?= (int)$it['cantidad'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge text-bg-secondary">
                      <?= (int)$it['entregado'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge text-bg-info">
                      <?= $cerrado ?>
                    </span>
                  </td>
                  <?php if (is_admin() && in_array($estado, ['CONFIRMADA', 'ENTREGADA'], true)): ?>
                    <td class="text-end text-nowrap">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-primary btnEditarPedido"
                        data-tipo="INV"
                        data-id="<?= (int)$it['id'] ?>"
                        data-detalle="<?= htmlspecialchars((string)$it['nombre'], ENT_QUOTES) ?>"
                        data-cantidad="<?= (int)$it['cantidad'] ?>"
                        data-entregado="<?= (int)$it['entregado'] ?>"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarPedido"
                      >
                        Editar
                      </button>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="eliminar_item_pedido">
                        <input type="hidden" name="detalle_id" value="<?= (int)$it['id'] ?>">
                        <button
                          type="submit"
                          class="btn btn-sm btn-outline-danger"
                          <?= (int)$it['entregado'] > 0 ? 'disabled title="No se puede quitar porque ya fue entregado"' : '' ?>
                          data-confirm="¿Quitar este artículo del pedido?"
                        >
                          Quitar
                        </button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
              <?php foreach ($extras as $e): ?>
                <?php
                  $cerrado = (int)$e['devuelto'];
                ?>
                <tr>
                  <td>
                    <span class="badge rounded-pill text-bg-dark">
                      Extra
                    </span>
                  </td>
                  <td>
                    <div class="fw-semibold">
                      <?= htmlspecialchars((string)$e['descripcion']) ?>
                    </div>
                    <?php if (!empty($e['proveedor'])): ?>
                      <div class="text-muted small">
                        Proveedor:
                        <?= htmlspecialchars((string)$e['proveedor']) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge text-bg-dark">
                      <?= (int)$e['cantidad'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge text-bg-secondary">
                      <?= (int)$e['entregado'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge text-bg-info">
                      <?= $cerrado ?>
                    </span>
                  </td>
                  <?php if (is_admin() && in_array($estado, ['CONFIRMADA', 'ENTREGADA'], true)): ?>
                    <td class="text-end text-nowrap">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-primary btnEditarPedido"
                        data-tipo="EXT"
                        data-id="<?= (int)$e['id'] ?>"
                        data-detalle="<?= htmlspecialchars((string)$e['descripcion'], ENT_QUOTES) ?>"
                        data-cantidad="<?= (int)$e['cantidad'] ?>"
                        data-entregado="<?= (int)$e['entregado'] ?>"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarPedido"
                      >
                        Editar
                      </button>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="eliminar_extra_pedido">
                        <input type="hidden" name="extra_id" value="<?= (int)$e['id'] ?>">
                        <button
                          type="submit"
                          class="btn btn-sm btn-outline-danger"
                          <?= (int)$e['entregado'] > 0 ? 'disabled title="No se puede quitar porque ya fue entregado"' : '' ?>
                          data-confirm="¿Quitar este extra del pedido?"
                        >
                          Quitar
                        </button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
              <?php if (!$items && !$extras): ?>
                <tr>
                  <td
                    colspan="<?= is_admin() && in_array($estado, ['CONFIRMADA', 'ENTREGADA'], true) ? 6 : 5 ?>"
                    class="text-center py-4 text-muted"
                  >
                    Sin artículos ni extras.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="small text-muted">
          * Inventario “Cerrado” = Devuelto + Dañado + Perdido
          de lo entregado.
          <br>
          * Extras “Cerrado” = Devuelto de lo entregado
          más observación opcional.
        </div>
      </div>
    </div>
  </div>
