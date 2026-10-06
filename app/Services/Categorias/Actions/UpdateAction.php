<?php
declare(strict_types=1);
namespace Marestu\Services\Categorias\Actions;

use Marestu\Services\ActionResult;

trait UpdateAction {
    public function update(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)($input['id'] ?? 0);
        $nombre = $this->normalize_cat((string)($input['nombre'] ?? ''));
        $activo = ((string)($input['activo'] ?? '1') === '1') ? 1 : 0;
        if ($id <= 0) {
            $result->message('err', "ID inválido.");
            return $result->redirect('categorias.php');
        }
        // ✅ Bloqueo: si quieren desactivar desde edición y tiene artículos -> NO
        if ($activo === 0) {
            $n = $this->repository->count_articulos_in_categoria($id);
            if ($n > 0) {
                $error = "No se puede desactivar: la categoría tiene {$n} artículo(s) asociados. Reasigná primero.";
            }
        }
        if (!$error) {
            if ($nombre === '') {
                $error = "El nombre es obligatorio.";
            } else {
                $chk = $this->repository->update_select_categorias([$nombre, $id]);
                if ($chk->fetch()) {
                    $error = "Ya existe otra categoría con ese nombre.";
                } else {
                    $up = $this->repository->update_update_categorias([$nombre, $activo, $id]);
                    $result->message('ok', "Categoría actualizada.");
                    return $result->redirect('categorias.php');
                }
            }
        }
        return $result->withData(['error' => $error]);
    }
}
