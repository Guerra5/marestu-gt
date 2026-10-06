<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaEdicion\Actions;

use Marestu\Services\ActionResult;

trait RemoveItemAction {
    public function remove_item(array $input, array $context, array $actor): ActionResult {
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
        $det_id = (int)($input['det_id'] ?? 0);
        $del = $this->repository->remove_item_delete_from_reserva_detalle([$det_id, $id]);
        $result->message('ok', "Artículo removido.");
        return $result->redirect("reserva_editar.php?id={$id}");

    }
}
