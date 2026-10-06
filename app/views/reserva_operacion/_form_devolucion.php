<form method="post" class="d-grid gap-2">
            <input
              type="hidden"
              name="csrf"
              value="<?= htmlspecialchars(csrf_token()) ?>"
            >
            <input
              type="hidden"
              name="action"
              value="devolver"
            >
            <div class="fw-semibold">
              Inventario
            </div>
            <?php foreach ($items as $it): ?>
              <?php
                $cerrado =
                  (int)$it['devuelto'] +
                  (int)$it['danado'] +
                  (int)$it['perdido'];
                $pend =
                  (int)$it['entregado'] -
                  $cerrado;
              ?>
              <div class="border rounded-3 p-2 mb-2 bg-light">
                <div class="fw-semibold">
                  <?= htmlspecialchars((string)$it['codigo']) ?>
                  · pendiente: <?= $pend ?>
                </div>
                <div class="row g-2 mt-1">
                  <div class="col-4">
                    <label class="form-label small">
                      Devuelto
                    </label>
                    <input
                      class="form-control"
                      type="number"
                      min="0"
                      max="<?= $pend ?>"
                      step="1"
                      inputmode="numeric"
                      name="dev_<?= (int)$it['id'] ?>"
                      value="0"
                    >
                  </div>
                  <div class="col-4">
                    <label class="form-label small">
                      Dañado
                    </label>
                    <input
                      class="form-control"
                      type="number"
                      min="0"
                      max="<?= $pend ?>"
                      step="1"
                      inputmode="numeric"
                      name="dan_<?= (int)$it['id'] ?>"
                      value="0"
                    >
                  </div>
                  <div class="col-4">
                    <label class="form-label small">
                      Perdido
                    </label>
                    <input
                      class="form-control"
                      type="number"
                      min="0"
                      max="<?= $pend ?>"
                      step="1"
                      inputmode="numeric"
                      name="per_<?= (int)$it['id'] ?>"
                      value="0"
                    >
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <div class="border rounded-3 p-3 bg-white">
              <label class="form-label fw-semibold">
                Observación bodega (opcional)
              </label>
              <textarea
                class="form-control"
                name="obs_bodega"
                rows="2"
                placeholder="Ej: manteles manchados, servilletas húmedas, faltan piezas..."
              ></textarea>
              <div class="small text-muted mt-1">
                * Esta observación se agrega a las notas de kardex
                cuando corresponde.
              </div>
            </div>
            <hr>
            <div class="fw-semibold">
              Extras
            </div>
            <?php foreach ($extras as $e): ?>
              <?php
                $pend =
                  (int)$e['entregado'] -
                  (int)$e['devuelto'];
              ?>
              <div class="border rounded-3 p-2 mb-2 bg-light">
                <div class="fw-semibold">
                  <?= htmlspecialchars((string)$e['descripcion']) ?>
                  · pendiente: <?= $pend ?>
                </div>
                <div class="row g-2 mt-1">
                  <div class="col-4">
                    <label class="form-label small">
                      Devuelto
                    </label>
                    <input
                      class="form-control"
                      type="number"
                      min="0"
                      max="<?= $pend ?>"
                      step="1"
                      inputmode="numeric"
                      name="dev_extra_<?= (int)$e['id'] ?>"
                      value="0"
                    >
                  </div>
                  <div class="col-8">
                    <label class="form-label small">
                      Observación (opcional)
                    </label>
                    <input
                      class="form-control"
                      name="obs_extra_<?= (int)$e['id'] ?>"
                      placeholder="Ej: se devolvió manchado, roto, incompleto..."
                    >
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <button
              class="btn btn-primary btn-pill"
              data-confirm="¿Registrar devolución/daños/pérdidas?"
            >
              Guardar devolución
            </button>
          </form>
