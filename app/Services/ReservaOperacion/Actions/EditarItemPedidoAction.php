<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaOperacion\Actions;

use Throwable;
use RuntimeException;
use Marestu\Services\ActionResult;

trait EditarItemPedidoAction {
    public function editar_item_pedido(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        $estado = (string)$res['estado'];
        if (!(($actor['rol'] ?? '') === 'ADMIN')) {
            $result->message('err', "Solo ADMIN puede editar artículos del pedido.");
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        if (!in_array($estado, ['CONFIRMADA', 'ENTREGADA'], true)) {
            $result->message('err', "Solo se puede editar un pedido CONFIRMADO o ENTREGADO.");
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        $detalle_id = (int)($input['detalle_id'] ?? 0);
        $nueva_cantidad = (int)($input['nueva_cantidad'] ?? 0);
        $this->repository->beginTransaction();
        $this->repository->editar_item_pedido_exec();
        try {
            $st = $this->repository->editar_item_pedido_select_reserva_detalle([$detalle_id, $id]);
            $row = $st->fetch();
            if (!$row) {
                throw new RuntimeException("El artículo del pedido no existe.");
            }
            $entregado = (int)$row['entregado'];
            $cantidad_actual = (int)$row['cantidad'];
            if ($nueva_cantidad <= 0) {
                throw new RuntimeException("La cantidad debe ser mayor que cero. Para quitarlo usá el botón Quitar.");
            }
            if ($nueva_cantidad < $entregado) {
                throw new RuntimeException("La cantidad no puede ser menor que lo ya entregado ({$entregado}).");
            }
            $diferencia = $nueva_cantidad - $cantidad_actual;
            $disponible = $this->repository->availableForReservation((int)$row['articulo_id'], (string)$res['fecha_salida'], (string)$res['fecha_retorno'], $id);
            if ($diferencia > 0 && $nueva_cantidad > $disponible) {
                throw new RuntimeException(
                    "Stock insuficiente para aumentar {$row['codigo']} ({$row['nombre']}). Disponible para este pedido: {$disponible}."
                );
            }
            $up = $this->repository->editar_item_pedido_update_reserva_detalle([$nueva_cantidad, $detalle_id, $id]);
            $this->sync_delivery_state($id);
            $this->repository->commit();
            $result->message('ok', "Cantidad del artículo actualizada.");
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
