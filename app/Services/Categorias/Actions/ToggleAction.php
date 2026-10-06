<?php
declare(strict_types=1);
namespace Marestu\Services\Categorias\Actions;

use Marestu\Services\ActionResult;

trait ToggleAction {
    public function toggle(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            $result->message('err', "ID inválido.");
            return $result->redirect('categorias.php');
        }
        $st = $this->repository->toggle_select_categorias([$id]);
        $row = $st->fetch();
        if (!$row) {
            $result->message('err', "Categoría no encontrada.");
            return $result->redirect('categorias.php');
        }
        $nuevo = ((int)$row['activo'] === 1) ? 0 : 1;
        // ✅ Bloqueo: si vamos a desactivar y tiene artículos -> NO
        if ($nuevo === 0) {
            $n = $this->repository->count_articulos_in_categoria($id);
            if ($n > 0) {
                $result->message('err', "No se puede desactivar: la categoría tiene {$n} artículo(s) asociados. Reasigná primero.");
                return $result->redirect('categorias.php');
            }
        }
        $up = $this->repository->toggle_update_categorias([$nuevo, $id]);
        $result->message('ok', $nuevo ? "Categoría activada." : "Categoría desactivada.");
        return $result->redirect('categorias.php');

    }
}
