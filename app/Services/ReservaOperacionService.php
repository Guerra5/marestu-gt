<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\ReservaOperacionRepository;

final class ReservaOperacionService {
    use ReservaOperacion\Actions\EntregarAction;
    use ReservaOperacion\Actions\DevolverAction;
    use ReservaOperacion\Actions\EditarItemPedidoAction;
    use ReservaOperacion\Actions\EliminarItemPedidoAction;
    use ReservaOperacion\Actions\EditarExtraPedidoAction;
    use ReservaOperacion\Actions\EliminarExtraPedidoAction;
    use ReservaOperacion\Actions\EntregaAdicionalAction;

    public function __construct(private readonly ReservaOperacionRepository $repository) {}

    private function all_delivered_inv(array $items): bool {
        foreach ($items as $it) {
            if ((int)$it['entregado'] < (int)$it['cantidad']) {
                return false;
            }
        }
        return true;
    }

    private function all_delivered_extras(array $extras): bool {
        foreach ($extras as $e) {
            if ((int)$e['entregado'] < (int)$e['cantidad']) {
                return false;
            }
        }
        return true;
    }

    private function sync_delivery_state(int $reserva_id): void {
        $items = $this->repository->get_detalle($reserva_id);
        $extras = $this->repository->get_extras($reserva_id);
        // Una reserva sin renglones no debe quedar como ENTREGADA.
        if (!$items && !$extras) {
            $this->repository->set_estado($reserva_id, 'CONFIRMADA');
            return;
        }
        if (
            $this->all_delivered_inv($items) &&
            $this->all_delivered_extras($extras)
        ) {
            $this->repository->set_estado($reserva_id, 'ENTREGADA');
        } else {
            $this->repository->set_estado($reserva_id, 'CONFIRMADA');
        }
    }

    private function all_closed_inv(array $items): bool {
        foreach ($items as $it) {
            $cerrado =
            (int)$it['devuelto'] +
            (int)$it['danado'] +
            (int)$it['perdido'];
            if ($cerrado < (int)$it['entregado']) {
                return false;
            }
        }
        return true;
    }

    private function all_closed_extras(array $extras): bool {
        foreach ($extras as $e) {
            if ((int)$e['devuelto'] < (int)$e['entregado']) {
                return false;
            }
        }
        return true;
    }

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        if (!$submitted) return $this->executeRequest($query, $input, $actor, false, $feedback);
        try {
            return $this->repository->withReservationTransaction(
                (int)($query['id'] ?? 0), (int)($input['articulo_id'] ?? 0),
                fn () => $this->executeRequest($query, $input, $actor, true, $feedback)
            );
        } catch (\Throwable $e) {
            error_log('Reserva: ' . $e->getMessage());
            $result = new ActionResult();
            $result->message('err', 'No se pudo completar la operación. Recargá la reserva e intentá nuevamente.');
            return $result->redirect('reserva_editar.php?id=' . (int)($query['id'] ?? 0));
        }
    }

    private function executeRequest(array $query, array $input, array $actor, bool $submitted, array $feedback): ActionResult {
        $result = new ActionResult();

        $id = (int)($query['id'] ?? 0);
        if ($id <= 0) {
            return $result->stop("ID inválido.");
        }
        $res = $this->repository->get_reserva($id);
        if (!$res) {
            return $result->stop("Reserva no encontrada.");
        }
        $estado = (string)$res['estado'];
        if (
            !in_array(
                $estado,
                ['CONFIRMADA', 'ENTREGADA', 'DEVUELTA', 'CANCELADA'],
                true
            )
        ) {
            $result->message(
                'err',
                "La operación diaria aplica cuando la reserva está CONFIRMADA o ENTREGADA."
            );
            return $result->redirect("reserva_editar.php?id={$id}");
        }
        $error = null;

        // POST

        if ($submitted) {
            $action = (string)($input['action'] ?? '');
            $items  = $this->repository->get_detalle($id);
            $extras = $this->repository->get_extras($id);

            $actionResult = match ($action) {
                'entregar' => $this->entregar($input, compact('id', 'res', 'items', 'extras'), $actor),
                'devolver' => $this->devolver($input, compact('id', 'res', 'items', 'extras'), $actor),
                'editar_item_pedido' => $this->editar_item_pedido($input, compact('id', 'res', 'items', 'extras'), $actor),
                'eliminar_item_pedido' => $this->eliminar_item_pedido($input, compact('id', 'res', 'items', 'extras'), $actor),
                'editar_extra_pedido' => $this->editar_extra_pedido($input, compact('id', 'res', 'items', 'extras'), $actor),
                'eliminar_extra_pedido' => $this->eliminar_extra_pedido($input, compact('id', 'res', 'items', 'extras'), $actor),
                'entrega_adicional' => $this->entrega_adicional($input, compact('id', 'res', 'items', 'extras'), $actor),
                default => (new ActionResult())->stop('Acción desconocida.', 400),
            };
            if ($actionResult->destination !== null || $actionResult->errorBody !== null) return $actionResult;
            extract($actionResult->data, EXTR_OVERWRITE);
        }

        // CARGA FINAL

        $res = $this->repository->get_reserva($id);
        $estado = (string)$res['estado'];
        $items  = $this->repository->get_detalle($id);
        $extras = $this->repository->get_extras($id);
        $arts   = $this->repository->get_articulos_activos();
        $ok  = ($feedback['ok'] ?? null);
        $err = ($feedback['err'] ?? null);
        return $result->withData(compact('id', 'res', 'estado', 'items', 'extras', 'arts', 'error', 'ok', 'err'));
    }
}
