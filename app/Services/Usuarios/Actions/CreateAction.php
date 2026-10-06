<?php
declare(strict_types=1);
namespace Marestu\Services\Usuarios\Actions;

use Marestu\Services\ActionResult;

trait CreateAction {
    public function create(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $nombre = trim((string)($input['nombre'] ?? ''));
        $usuario = trim((string)($input['usuario'] ?? ''));
        $rol = (string)($input['rol'] ?? 'OPERADOR');
        $activo = ((string)($input['activo'] ?? '1') === '1') ? 1 : 0;
        $pass1 = (string)($input['pass1'] ?? '');
        $pass2 = (string)($input['pass2'] ?? '');
        if ($nombre === '' || $usuario === '') {
            $error = "Nombre y usuario son obligatorios.";
        } elseif (!in_array($rol, ['ADMIN','OPERADOR'], true)) {
            $error = "Rol inválido.";
        } elseif ($pass1 === '' || $pass2 === '') {
            $error = "La contraseña es obligatoria.";
        } elseif ($pass1 !== $pass2) {
            $error = "Las contraseñas no coinciden.";
        } elseif (strlen($pass1) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres.";
        } else {
            $st = $this->repository->create_select_usuarios([$usuario]);
            if ($st->fetch()) {
                $error = "Ese usuario ya existe.";
            } else {
                $hash = password_hash($pass1, PASSWORD_BCRYPT);
                $ins = $this->repository->create_insert_into_usuarios([$nombre, $usuario, $hash, $rol, $activo]);
                $result->message('ok', "Usuario creado correctamente.");
                return $result->redirect('usuarios.php');
            }
        }
        return $result->withData(['error' => $error]);
    }
}
