<?php
declare(strict_types=1);
namespace Marestu\Services\Usuarios\Actions;

use Marestu\Services\ActionResult;

trait ToggleAction {
    public function toggle(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            $result->message('err', "ID inválido.");
            return $result->redirect('usuarios.php');
        }
        if ($id === (int)$actor['id']) {
            $result->message('err', "No se permite desactivar tu propio usuario.");
            return $result->redirect('usuarios.php');
        }
        $st = $this->repository->toggle_select_usuarios([$id]);
        $row = $st->fetch();
        if (!$row) {
            $result->message('err', "Usuario no encontrado.");
            return $result->redirect('usuarios.php');
        }
        $nuevo = ((int)$row['activo'] === 1) ? 0 : 1;
        $up = $this->repository->toggle_update_usuarios([$nuevo, $id]);
        $result->message('ok', $nuevo ? "Usuario activado." : "Usuario desactivado.");
        return $result->redirect('usuarios.php');

    }
}
