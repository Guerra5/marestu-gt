<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaEdicion\Actions;

use Marestu\Services\ActionResult;

trait DireccionEventoAction {
    public function direccion_evento(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        $direccion = trim((string)($input['direccion_evento'] ?? ''));
        if (!in_array($res['estado'], ['BORRADOR', 'CONFIRMADA'], true)) {
            $result->message('err', 'Solo se puede cambiar la dirección antes de completar la entrega.');
        } elseif (strlen($direccion) > 8000) {
            $result->message('err', 'La dirección del evento es demasiado larga.');
        } else {
            $st = $this->repository->direccion_evento_update_reservas([$direccion !== '' ? $direccion : null, $id]);
            $result->message('ok', 'Dirección del evento guardada.');
        }
        return $result->redirect("reserva_editar.php?id={$id}");

    }
}
