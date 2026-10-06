<?php
declare(strict_types=1);
namespace Marestu\Services\Usuarios\Actions;

use Marestu\Services\ActionResult;

trait ResetAction {
    public function reset(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            $result->message('err', "ID inválido.");
            return $result->redirect('usuarios.php');
        }
        $st = $this->repository->reset_select_usuarios([$id]);
        $row = $st->fetch();
        if (!$row) {
            $result->message('err', "Usuario no encontrado.");
            return $result->redirect('usuarios.php');
        }
        $temp = $this->gen_temp_password(10);
        $hash = password_hash($temp, PASSWORD_BCRYPT);
        $up = $this->repository->reset_update_usuarios([$hash, $id]);
        $result->message('ok', "Contraseña reseteada para @".(string)$row['usuario'].".");
        // mostrar una sola vez:
        $result->message('temp', '1');
        $result->message('temp_pass', $temp); // se consume al cargar
        return $result->redirect('usuarios.php');

    }
}
