<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaEdicion\Actions;

use Throwable;
use Marestu\Services\ActionResult;

trait SetStatusAction {
    public function set_status(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        $to = (string)($input['to'] ?? '');
        $valid = [
            'BORRADOR',
            'CONFIRMADA',
            'ENTREGADA',
            'DEVUELTA',
            'CANCELADA'
        ];
        if (!in_array($to, $valid, true)) {
            $result->message('err', "Estado inválido.");
            return $result->redirect("reserva_editar.php?id={$id}");
        }
        if (in_array($to, ['ENTREGADA', 'DEVUELTA'], true)) {
            $result->message(
                'err',
                "ENTREGADA/DEVUELTA se gestionan desde Operación diaria."
            );
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        $from = (string)$res['estado'];
        $allowed = [
            'BORRADOR'   => ['CONFIRMADA', 'CANCELADA'],
            'CONFIRMADA' => ['CANCELADA'],
            'ENTREGADA'  => [],
            'DEVUELTA'   => [],
            'CANCELADA'  => [],
        ];
        if (!in_array($to, $allowed[$from] ?? [], true)) {
            $result->message(
                'err',
                "Transición no permitida ({$from} → {$to})."
            );
            return $result->redirect("reserva_editar.php?id={$id}");
        }

        // Confirmar reserva

        if ($to === 'CONFIRMADA') {
            $items_now = $this->repository->reserva_items($id);
            $extras_now = $this->repository->reserva_extras($id);
            if (!$items_now && !$extras_now) {
                $result->message(
                    'err',
                    "No podés confirmar sin artículos o extras."
                );
                return $result->redirect("reserva_editar.php?id={$id}");
            }
            if ($items_now) {
                $salida = (string)$res['fecha_salida'];
                $retorno = (string)$res['fecha_retorno'];
                foreach ($items_now as $it) {
                    $articulo_id = (int)$it['articulo_id'];
                    $qty = (int)$it['cantidad'];
                    $stock = $this->repository->articulo_activo_stock($articulo_id
                    );
                    $reservado = $this->repository->reserved_qty($articulo_id,
                        $salida,
                        $retorno,
                        $id
                    );
                    $disponible = $stock - $reservado;
                    if ($qty > $disponible) {
                        $result->message(
                            'err',
                            "No hay disponibilidad para confirmar: {$it['codigo']} ({$it['nombre']}). Disponible: {$disponible}, requerido: {$qty}."
                        );
                        return $result->redirect("reserva_editar.php?id={$id}");
                    }
                }
            }
            $this->repository->beginTransaction();
            try {
                $up = $this->repository->set_status_update_reservas([$id]);
                if ($items_now) {
                    foreach ($items_now as $it) {
                        $nota = "Reserva CONFIRMADA. Rango {$res['fecha_salida']} a {$res['fecha_retorno']}. Cantidad: {$it['cantidad']}.";
                        $this->repository->add_mov('RESERVA',
                            (int)$it['articulo_id'],
                            $id,
                            0,
                            $nota,
                            (int)$actor['id']
                        );
                    }
                }
                $this->repository->commit();
                $result->message('ok', "Reserva confirmada.");
                return $result->redirect("reserva_editar.php?id={$id}");
            } catch (Throwable $e) {
                $this->repository->rollBack();
                $result->message('err', $e->getMessage());
                return $result->redirect("reserva_editar.php?id={$id}");
            }
        }

        // Cancelar reserva

        if ($to === 'CANCELADA') {
            if ($this->repository->hasOutstandingDelivery($id)) {
                $result->message('err', 'No se puede cancelar: hay artículos o extras entregados pendientes de devolución.');
                return $result->redirect("reserva_editar.php?id={$id}");
            }
            $up = $this->repository->set_status_update_reservas_2([$id]);
            $result->message('ok', "Reserva cancelada.");
            return $result->redirect("reserva_editar.php?id={$id}");
        }
        $result->message('err', "Acción no aplicada.");
        return $result->redirect("reserva_editar.php?id={$id}");

    }
}
