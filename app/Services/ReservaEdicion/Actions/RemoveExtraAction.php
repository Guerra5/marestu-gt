<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaEdicion\Actions;

use Marestu\Services\ActionResult;

trait RemoveExtraAction {
    public function remove_extra(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        if ($res['estado'] !== 'BORRADOR') {
            $result->message(
                'err',
                "Solo podés modificar extras en estado BORRADOR."
            );
            return $result->redirect("reserva_editar.php?id={$id}");
        }
        $extra_id = (int)($input['extra_id'] ?? 0);
        $del = $this->repository->remove_extra_delete_from_reserva_extras([$extra_id, $id]);
        $result->message('ok', "Extra removido.");
        return $result->redirect("reserva_editar.php?id={$id}");

    }
}
