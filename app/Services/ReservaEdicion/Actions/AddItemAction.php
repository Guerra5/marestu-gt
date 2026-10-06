<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaEdicion\Actions;

use Marestu\Services\ActionResult;

trait AddItemAction {
    public function add_item(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        if ($res['estado'] !== 'BORRADOR') {
            $result->message(
                'err',
                "Solo podés modificar artículos en estado BORRADOR."
            );
            return $result->redirect("reserva_editar.php?id={$id}");
        }
        $articulo_id = (int)($input['articulo_id'] ?? 0);
        $cantidad = (int)($input['cantidad'] ?? 0);
        if ($articulo_id <= 0) {
            $error = "Seleccioná un artículo.";
        } elseif ($cantidad <= 0) {
            $error = "Cantidad inválida.";
        } else {
            $salida = (string)$res['fecha_salida'];
            $retorno = (string)$res['fecha_retorno'];
            $stock = $this->repository->articulo_activo_stock($articulo_id);
            $reservado = $this->repository->reserved_qty($articulo_id,
                $salida,
                $retorno,
                $id
            );
            $disponible = $stock - $reservado;
            $stx = $this->repository->add_item_select_reserva_detalle([$id, $articulo_id]);
            $exists = $stx->fetch();
            $max_para_esta_reserva = $disponible;
            if ($cantidad > $max_para_esta_reserva) {
                $error = "No hay disponibilidad suficiente para esas fechas. Disponible: {$max_para_esta_reserva}.";
            } else {
                if ($exists) {
                    $upd = $this->repository->add_item_update_reserva_detalle([
                            $cantidad,
                            (int)$exists['id']
                    ]);
                } else {
                    $ins = $this->repository->add_item_insert_into_reserva_detalle([
                            $id,
                            $articulo_id,
                            $cantidad
                    ]);
                }
                $result->message(
                    'ok',
                    "Artículo agregado/actualizado en la reserva."
                );
                return $result->redirect("reserva_editar.php?id={$id}");
            }
        }
        return $result->withData(['error' => $error]);
    }
}
