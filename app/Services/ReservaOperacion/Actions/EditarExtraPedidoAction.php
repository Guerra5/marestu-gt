<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaOperacion\Actions;

use Throwable;
use RuntimeException;
use Marestu\Services\ActionResult;

trait EditarExtraPedidoAction {
    public function editar_extra_pedido(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        $estado = (string)$res['estado'];
        if (!(($actor['rol'] ?? '') === 'ADMIN')) {
            $result->message('err', "Solo ADMIN puede editar extras del pedido.");
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        if (!in_array($estado, ['CONFIRMADA', 'ENTREGADA'], true)) {
            $result->message('err', "Solo se puede editar un pedido CONFIRMADO o ENTREGADO.");
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        $extra_id = (int)($input['extra_id'] ?? 0);
        $nueva_cantidad = (int)($input['nueva_cantidad'] ?? 0);
        $this->repository->beginTransaction();
        $this->repository->editar_extra_pedido_exec();
        try {
            $st = $this->repository->editar_extra_pedido_select_reserva_extras([$extra_id, $id]);
            $row = $st->fetch();
            if (!$row) {
                throw new RuntimeException("El extra del pedido no existe.");
            }
            $entregado = (int)$row['entregado'];
            if ($nueva_cantidad <= 0) {
                throw new RuntimeException("La cantidad debe ser mayor que cero. Para quitarlo usá el botón Quitar.");
            }
            if ($nueva_cantidad < $entregado) {
                throw new RuntimeException("La cantidad no puede ser menor que lo ya entregado ({$entregado}).");
            }
            $nuevo_subtotal = $nueva_cantidad * (float)$row['precio_unitario'];
            $up = $this->repository->editar_extra_pedido_update_reserva_extras([$nueva_cantidad, $nuevo_subtotal, $extra_id, $id]);
            $this->sync_delivery_state($id);
            $this->repository->commit();
            $result->message('ok', "Cantidad del extra actualizada.");
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
