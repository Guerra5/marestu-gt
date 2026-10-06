<?php
declare(strict_types=1);
namespace Marestu\Services\Login\Actions;

use Marestu\Services\ActionResult;

trait SaveAction {
    public function save(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $usuario = trim((string)($input['usuario'] ?? ''));
        $pass = (string)($input['password'] ?? '');
        if ($usuario === '' || $pass === '') {
            $error = "Ingresá usuario y contraseña.";
        } else {

            $st = $this->repository->save_select_usuarios([$usuario]);
            $u = $st->fetch();
            if (!$u || (int)$u['activo'] !== 1) {
                $error = "Usuario o contraseña inválidos.";
            } elseif (!password_verify($pass, (string)$u['password_hash'])) {
                $error = "Usuario o contraseña inválidos.";
            } else {
                // login ok
                $result->authenticatedUser = [
                    'id' => (int)$u['id'],
                    'nombre' => (string)$u['nombre'],
                    'usuario' => (string)$u['usuario'],
                    'rol' => (string)$u['rol'],
                ];
                return $result->redirect('index.php');
            }
        }
        return $result->withData(['error' => $error]);
    }
}
