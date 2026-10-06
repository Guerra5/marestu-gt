<?php
declare(strict_types=1);
namespace Marestu\Services\Categorias\Actions;

use Marestu\Services\ActionResult;

trait CreateAction {
    public function create(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $nombre = $this->normalize_cat((string)($input['nombre'] ?? ''));
        $activo = ((string)($input['activo'] ?? '1') === '1') ? 1 : 0;
        if ($nombre === '') {
            $error = "El nombre es obligatorio.";
        } else {
            // Único (case-insensitive)
            $chk = $this->repository->create_select_categorias([$nombre]);
            if ($chk->fetch()) {
                $error = "Esa categoría ya existe.";
            } else {
                $ins = $this->repository->create_insert_into_categorias([$nombre, $activo]);
                $result->message('ok', "Categoría creada.");
                return $result->redirect('categorias.php');
            }
        }
        return $result->withData(['error' => $error]);
    }
}
