<?php
declare(strict_types=1);
namespace Marestu\Services\UsuarioNuevo\Actions;

use Marestu\Services\ActionResult;

trait SaveAction {
    public function save(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $nombre = trim((string)($input['nombre'] ?? ''));
        $usuario = trim((string)($input['usuario'] ?? ''));
        $rol = (string)($input['rol'] ?? 'OPERADOR');
        $activo = isset($input['activo']) ? 1 : 0;
        $pass1 = (string)($input['pass1'] ?? '');
        $pass2 = (string)($input['pass2'] ?? '');
        if ($nombre === '' || $usuario === '') {
            $error = "Nombre y usuario son obligatorios.";
        } elseif (!in_array($rol, ['ADMIN','OPERADOR'], true)) {
            $error = "Rol inválido.";
        } elseif ($pass1 === '' || $pass2 === '') {
            $error = "Contraseña obligatoria.";
        } elseif ($pass1 !== $pass2) {
            $error = "Las contraseñas no coinciden.";
        } elseif (strlen($pass1) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres.";
        } else {
            // usuario único
            $st = $this->repository->save_select_usuarios([$usuario]);
            if ($st->fetch()) {
                $error = "Ese usuario ya existe.";
            } else {
                $hash = password_hash($pass1, PASSWORD_BCRYPT);
                $ins = $this->repository->save_insert_into_usuarios([$nombre, $usuario, $hash, $rol, $activo]);
                $result->message('ok', "Usuario creado correctamente.");
                return $result->redirect('usuarios.php');
            }
        }
        return $result->withData(compact('error', 'nombre', 'usuario', 'rol', 'activo'));
    }
}
