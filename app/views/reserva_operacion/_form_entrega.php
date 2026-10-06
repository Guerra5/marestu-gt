<form method="post" class="d-grid gap-2">
            <input
              type="hidden"
              name="csrf"
              value="<?= htmlspecialchars(csrf_token()) ?>"
            >
            <input
              type="hidden"
              name="action"
              value="entregar"
            >
            <div class="fw-semibold mt-2">
              Inventario
            </div>
            <?php foreach ($items as $it): ?>
              <?php
                $pend =
                  (int)$it['cantidad'] -
                  (int)$it['entregado'];
              ?>
              <label class="form-label mb-1">
                <?= htmlspecialchars((string)$it['codigo']) ?>
                (pendiente: <?= $pend ?>)
              </label>
              <input
                class="form-control mb-2"
                type="number"
                min="0"
                max="<?= $pend ?>"
                step="1"
                inputmode="numeric"
                name="entrega_<?= (int)$it['id'] ?>"
                value="0"
              >
            <?php endforeach; ?>
            <div class="fw-semibold mt-2">
              Extras
            </div>
            <?php foreach ($extras as $e): ?>
              <?php
                $pend =
                  (int)$e['cantidad'] -
                  (int)$e['entregado'];
              ?>
              <label class="form-label mb-1">
                <?= htmlspecialchars((string)$e['descripcion']) ?>
                (pendiente: <?= $pend ?>)
              </label>
              <input
                class="form-control mb-2"
                type="number"
                min="0"
                max="<?= $pend ?>"
                step="1"
                inputmode="numeric"
                name="entrega_extra_<?= (int)$e['id'] ?>"
                value="0"
              >
            <?php endforeach; ?>
            <button
              class="btn btn-success btn-pill"
              data-confirm="¿Registrar entrega? Esto generará SALIDAS en kardex para inventario."
            >
              Guardar entrega
            </button>
          </form>
