<?php
declare(strict_types=1);
namespace Marestu\Services\Reservas\Actions;

use Throwable;
use Marestu\Services\ActionResult;

trait SaveAction {
    public function save(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $hoy = date('Y-m-d');
        $form_direccion_evento = trim((string)($input['direccion_evento'] ?? ''));
        $cliente_id   = (int)($input['cliente_id'] ?? 0);
        $fecha_salida = trim((string)($input['fecha_salida'] ?? ''));
        $fecha_evento = trim((string)($input['fecha_evento'] ?? ''));
        $fecha_retorno = trim((string)($input['fecha_retorno'] ?? ''));
        $nota = trim((string)($input['nota'] ?? ''));
        // Validaciones obligatorias
        if (strlen($form_direccion_evento) > 8000) {
            $error = 'La dirección del evento es demasiado larga.';
        } elseif ($cliente_id <= 0) {
            $error = 'Seleccioná un cliente.';
        } elseif (
            $fecha_salida === '' ||
            $fecha_evento === '' ||
            $fecha_retorno === ''
        ) {
            $error = 'Las fechas de salida, evento y retorno son obligatorias.';
        } elseif (
            !$this->valid_date($fecha_salida) ||
            !$this->valid_date($fecha_evento) ||
            !$this->valid_date($fecha_retorno)
        ) {
            $error = 'Una o más fechas no tienen un formato válido.';
        } elseif ($fecha_salida < $hoy) {
            $error = "No podés crear una reserva con fecha de salida anterior a hoy ({$hoy}).";
        } elseif ($fecha_evento < $fecha_salida) {
            $error = 'La fecha del evento no puede ser anterior a la fecha de salida.';
        } elseif ($fecha_retorno < $fecha_evento) {
            $error = 'La fecha de retorno no puede ser anterior a la fecha del evento.';
        } else {
            /*
             * Verificamos que el cliente enviado exista y esté activo.
             */
            $stCliente = $this->repository->save_select_clientes([$cliente_id]);
            if (!$stCliente->fetch()) {
                $error = 'El cliente seleccionado no existe o está inactivo.';
            } else {
                try {
                    $this->repository->beginTransaction();
                    $codigo = $this->repository->next_res_code();
                    $ins = $this->repository->save_insert_into_reservas([
                            $codigo,
                            $cliente_id,
                            $fecha_salida,
                            $fecha_evento,
                            $fecha_retorno,
                            ($nota !== '' ? $nota : null),
                            (int)$actor['id'],
                            ($form_direccion_evento !== '' ? $form_direccion_evento : null)
                    ]);
                    $id = (int)$this->repository->lastInsertId();
                    $this->repository->commit();
                    $result->message(
                        'ok',
                        "Reserva creada: {$codigo}. Agregá los artículos correspondientes."
                    );
                    return $result->redirect("reserva_editar.php?id={$id}");
                } catch (Throwable $e) {
                    if ($this->repository->inTransaction()) {
                        $this->repository->rollBack();
                    }
                    $error = 'No fue posible crear la reserva: ' . $e->getMessage();
                }
            }
        }
        return $result->withData(['error' => $error]);
    }
}
