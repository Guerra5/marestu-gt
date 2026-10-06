<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaOperacion\Actions;

use Throwable;
use RuntimeException;
use Marestu\Services\ActionResult;

trait EliminarItemPedidoAction {
    public function eliminar_item_pedido(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        $estado = (string)$res['estado'];
        if (!(($actor['rol'] ?? '') === 'ADMIN')) {
            $result->message('err', "Solo ADMIN puede quitar artículos del pedido.");
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        if (!in_array($estado, ['CONFIRMADA', 'ENTREGADA'], true)) {
            $result->message('err', "Solo se puede quitar de un pedido CONFIRMADO o ENTREGADO.");
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        $detalle_id = (int)($input['detalle_id'] ?? 0);
        $this->repository->beginTransaction();
        $this->repository->eliminar_item_pedido_exec();
        try {
            $st = $this->repository->eliminar_item_pedido_select_reserva_detalle([$detalle_id, $id]);
            $row = $st->fetch();
            if (!$row) {
                throw new RuntimeException("El artículo del pedido no existe.");
            }
            if (
                (int)$row['entregado'] > 0 ||
                (int)$row['devuelto'] > 0 ||
                (int)$row['danado'] > 0 ||
                (int)$row['perdido'] > 0
            ) {
                throw new RuntimeException("No se puede quitar porque ya tiene movimientos de entrega o devolución.");
            }
            $del = $this->repository->eliminar_item_pedido_delete_from_reserva_detalle([$detalle_id, $id]);
            $this->sync_delivery_state($id);
            $this->repository->commit();
            $result->message('ok', "Artículo quitado del pedido.");
            return $result->redirect("reserva_operacion.php?id={$id}");
        } catch (Throwable $e) {
            if ($this->repository->inTransaction()) {
                $this->repository->rollBack();
            }
            $error = $e->getMessage();
        }
        return $result->withData(['error' => $error]);
    }
}
