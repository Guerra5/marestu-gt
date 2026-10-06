<?php
declare(strict_types=1);
namespace Marestu\Services\Articulos\Actions;

use Marestu\Services\ActionResult;

trait ToggleAction {
    public function toggle(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            $result->message('err', "ID inválido.");
            return $result->redirect('articulos.php');
        }
        $st = $this->repository->toggle_select_articulos([$id]);
        $row = $st->fetch();
        if (!$row) {
            $result->message('err', "Artículo no encontrado.");
            return $result->redirect('articulos.php');
        }
        $nuevo = ((string)$row['estado'] === 'ACTIVO') ? 'INACTIVO' : 'ACTIVO';
        $up = $this->repository->toggle_update_articulos([$nuevo, $id]);
        $result->message('ok', $nuevo === 'ACTIVO' ? "Artículo activado." : "Artículo desactivado.");
        return $result->redirect('articulos.php');

    }
}
