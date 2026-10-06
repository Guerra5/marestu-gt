<?php
declare(strict_types=1);
namespace Marestu\Services\Clientes\Actions;

use Marestu\Services\ActionResult;

trait ToggleAction {
    public function toggle(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            $result->message('err', "ID inválido.");
            return $result->redirect('clientes.php');
        }
        $st = $this->repository->toggle_select_clientes([$id]);
        $row = $st->fetch();
        if (!$row) {
            $result->message('err', "Cliente no encontrado.");
            return $result->redirect('clientes.php');
        }
        $nuevo = ((string)$row['estado'] === 'ACTIVO') ? 'INACTIVO' : 'ACTIVO';
        $up = $this->repository->toggle_update_clientes([$nuevo, $id]);
        $result->message('ok', $nuevo === 'ACTIVO' ? "Cliente activado." : "Cliente desactivado.");
        return $result->redirect('clientes.php');

    }
}
