<?php
declare(strict_types=1);
namespace Marestu\Services\UsuarioEdicion\Actions;

use Throwable;
use RuntimeException;
use Marestu\Services\ActionResult;

trait SaveAction {
    public function save(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
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
        } else {
            // usuario único (excepto el mismo)
            $chk = $this->repository->save_select_usuarios([$usuario, $id]);
            if ($chk->fetch()) {
                $error = "Ese usuario ya existe.";
            } else {
                $this->repository->beginTransaction();
                try {
                    $up = $this->repository->save_update_usuarios([$nombre, $usuario, $rol, $activo, $id]);
                    // Cambiar password solo si lo llenan
                    if ($pass1 !== '' || $pass2 !== '') {
                        if ($pass1 !== $pass2) throw new RuntimeException("Las contraseñas no coinciden.");
                        if (strlen($pass1) < 6) throw new RuntimeException("La contraseña debe tener al menos 6 caracteres.");
                        $hash = password_hash($pass1, PASSWORD_BCRYPT);
                        $up2 = $this->repository->save_update_usuarios_2([$hash, $id]);
                    }
                    $this->repository->commit();
                    $result->message('ok', "Usuario actualizado.");
                    return $result->redirect('usuarios.php');
                } catch (Throwable $e) {
                    $this->repository->rollBack();
                    $error = $e->getMessage();
                }
            }
        }
        return $result->withData(compact('error', 'nombre', 'usuario', 'rol', 'activo'));
    }
}
