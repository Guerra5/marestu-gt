<div class="col-12 col-lg-4">
    <div class="card card-soft shadow-sm mb-3">
      <div class="card-body p-4">
        <h5 class="mb-3">
          Totales
        </h5>
        <div class="d-flex justify-content-between">
          <div class="text-muted">
            Inventario
          </div>
          <div class="fw-semibold">
            Q <?= number_format($total_inv, 2) ?>
          </div>
        </div>
        <div class="d-flex justify-content-between">
          <div class="text-muted">
            Extras
          </div>
          <div class="fw-semibold">
            Q <?= number_format($total_ext, 2) ?>
          </div>
        </div>
        <hr>
        <div class="d-flex justify-content-between">
          <div class="fw-semibold">
            Total
          </div>
          <div class="fw-bold fs-5">
            Q <?= number_format($total_gral, 2) ?>
          </div>
        </div>
        <div class="small text-muted mt-2">
          * Total = (artículos × precio unitario) + extras.
        </div>
      </div>
    </div>
    <div class="card card-soft shadow-sm">
      <div class="card-body p-4">
        <h5 class="mb-3">
          Acciones
        </h5>
        <div class="alert alert-info small">
          Flujo:
          <b>BORRADOR → CONFIRMADA → ENTREGADA → DEVUELTA</b>
          <br>
          Cancelación:
          <b>BORRADOR/CONFIRMADA → CANCELADA</b>
        </div>
        <?php if ($res['estado'] === 'BORRADOR'): ?>
          <a
            class="btn btn-outline-dark btn-pill w-100 mb-2"
            target="_blank"
            href="reserva_cotizacion.php?id=<?= (int)$id ?>"
          >
            Ver / Imprimir Cotización
          </a>
        <?php endif; ?>
        <?php if (
          in_array(
            $res['estado'],
            ['CONFIRMADA', 'ENTREGADA', 'DEVUELTA'],
            true
          )
        ): ?>
          <a
            class="btn btn-outline-dark btn-pill w-100 mb-2"
            target="_blank"
            href="reserva_nota_entrega.php?id=<?= (int)$id ?>"
          >
            Ver / Imprimir Nota de entrega
          </a>
        <?php endif; ?>
        <a
          class="btn btn-outline-primary btn-pill w-100 mb-2"
          href="reserva_operacion.php?id=<?= (int)$id ?>"
        >
          Operación diaria (entrega/devolución)
        </a>
        <?php if (can('reservas.gestionar')): ?>
        <form method="post" class="d-grid gap-2">
          <input
            type="hidden"
            name="csrf"
            value="<?= htmlspecialchars(csrf_token()) ?>"
          >
          <input
            type="hidden"
            name="action"
            value="set_status"
          >
          <?php if (can('reservas.gestionar') && $res['estado'] === 'BORRADOR'): ?>
            <button
              class="btn btn-success btn-pill"
              name="to"
              value="CONFIRMADA"
              data-confirm="¿Confirmar reserva? Se validará disponibilidad."
            >
              Confirmar
            </button>
            <button
              class="btn btn-outline-danger btn-pill"
              name="to"
              value="CANCELADA"
              data-confirm="¿Cancelar reserva?"
            >
              Cancelar
            </button>
          <?php elseif (
            $res['estado'] === 'CONFIRMADA' ||
            $res['estado'] === 'ENTREGADA'
          ): ?>
            <div class="alert alert-warning small mb-2">
              La entrega/devolución se gestiona desde
              <b>Operación diaria</b>.
            </div>
            <?php if ($res['estado'] === 'CONFIRMADA'): ?>
              <button
                class="btn btn-outline-danger btn-pill"
                name="to"
                value="CANCELADA"
                data-confirm="¿Cancelar reserva confirmada?"
              >
                Cancelar
              </button>
            <?php else: ?>
              <button
                class="btn btn-outline-secondary btn-pill"
                disabled
              >
                Sin acciones aquí
              </button>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-muted">
              No hay acciones disponibles en este estado.
            </div>
          <?php endif; ?>
        </form>
        <?php endif; ?>
        <hr>
        <div class="small text-muted">
          * Disponibilidad se bloquea con reservas
          <b>CONFIRMADAS</b> y <b>ENTREGADAS</b>
          que se traslapan en fechas.
        </div>
      </div>
    </div>
  </div>
