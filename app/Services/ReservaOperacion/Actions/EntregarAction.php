<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaOperacion\Actions;

use Throwable;
use RuntimeException;
use Marestu\Services\ActionResult;

trait EntregarAction {
    public function entregar(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        $estado = (string)$res['estado'];
        $items = $context['items'];
        $extras = $context['extras'];
        if ($estado !== 'CONFIRMADA') {
            $result->message(
                'err',
                "Solo podés registrar entregas cuando está CONFIRMADA."
            );
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        $this->repository->beginTransaction();
        $this->repository->entregar_exec();
        try {
            // Inventario
            foreach ($items as $it) {
                $det_id = (int)$it['id'];
                $key = "entrega_{$det_id}";
                $add = (int)($input[$key] ?? 0);
                if ($add <= 0) {
                    continue;
                }
                $pendiente =
                (int)$it['cantidad'] -
                (int)$it['entregado'];
                if ($add > $pendiente) {
                    throw new RuntimeException(
                        "Entrega excede pendiente en {$it['codigo']} ({$it['nombre']}). Pendiente: {$pendiente}."
                    );
                }
                $up = $this->repository->entregar_update_reserva_detalle([
                        $add,
                        $det_id,
                        $id
                ]);
                $nota = "Entrega parcial. Reserva {$res['codigo']}.";
                $this->repository->add_mov('SALIDA',
                    (int)$it['articulo_id'],
                    $id,
                    -$add,
                    $nota,
                    (int)$actor['id']
                );
            }
            // Extras
            foreach ($extras as $e) {
                $eid = (int)$e['id'];
                $key = "entrega_extra_{$eid}";
                $add = (int)($input[$key] ?? 0);
                if ($add <= 0) {
                    continue;
                }
                $pendiente =
                (int)$e['cantidad'] -
                (int)$e['entregado'];
                if ($add > $pendiente) {
                    throw new RuntimeException(
                        "Entrega excede pendiente en EXTRA: {$e['descripcion']}. Pendiente: {$pendiente}."
                    );
                }
                $up = $this->repository->entregar_update_reserva_extras([
                        $add,
                        $eid,
                        $id
                ]);
            }
            $items2  = $this->repository->get_detalle($id);
            $extras2 = $this->repository->get_extras($id);
            if (
                $this->all_delivered_inv($items2) &&
                $this->all_delivered_extras($extras2)
            ) {
                $this->repository->set_estado($id, 'ENTREGADA');
            }
            $this->repository->commit();
            $result->message('ok', "Entrega registrada.");
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
