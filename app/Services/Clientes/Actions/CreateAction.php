<?php
declare(strict_types=1);
namespace Marestu\Services\Clientes\Actions;

use Marestu\Services\ActionResult;

trait CreateAction {
    public function create(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $nombres = $this->norm((string)($input['nombres'] ?? ''));
        $apellidos = $this->norm((string)($input['apellidos'] ?? ''));
        $telefono = $this->norm((string)($input['telefono'] ?? ''));
        $email = $this->norm_email($input['email'] ?? null);
        $direccion = $this->norm((string)($input['direccion'] ?? ''));
        $nit = $this->norm((string)($input['nit'] ?? ''));
        $observaciones = trim((string)($input['observaciones'] ?? ''));
        $estado = ((string)($input['estado'] ?? 'ACTIVO') === 'INACTIVO') ? 'INACTIVO' : 'ACTIVO';
        if ($nombres === '' || $apellidos === '') {
            $error = "Nombres y apellidos son obligatorios.";
        } elseif ($telefono === '') {
            $error = "El teléfono es obligatorio.";
        } elseif (isset($input['email']) && trim((string)$input['email']) !== '' && $email === null) {
            $error = "Email inválido.";
        } else {
            $ins = $this->repository->create_insert_into_clientes([
                    $nombres,
                    $apellidos,
                    $telefono,
                    $email,
                    ($direccion !== '' ? $direccion : null),
                    ($nit !== '' ? $nit : null),
                    ($observaciones !== '' ? $observaciones : null),
                    $estado
            ]);
            $result->message('ok', "Cliente creado.");
            return $result->redirect('clientes.php');
        }
        return $result->withData(['error' => $error]);
    }
}
