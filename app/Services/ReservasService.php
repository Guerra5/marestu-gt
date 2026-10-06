<?php
declare(strict_types=1);
namespace Marestu\Services;

use DateTimeImmutable;
use Marestu\Repositories\ReservasRepository;

final class ReservasService {
    use Reservas\Actions\SaveAction;

    public function __construct(private readonly ReservasRepository $repository) {}

    private function valid_date(string $date): bool {
        if ($date === '') {
            return false;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false) {
            return false;
        }
        if (
            is_array($errors) &&
            ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
        ) {
            return false;
        }
        return $parsed->format('Y-m-d') === $date;
    }

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();

        $error = null;
        $hoy = date('Y-m-d');
        /*
         * Valores para conservar el formulario cuando exista un error.
         */
        $form_cliente_id   = (int)($input['cliente_id'] ?? 0);
        $form_fecha_salida = trim((string)($input['fecha_salida'] ?? ''));
        $form_fecha_evento = trim((string)($input['fecha_evento'] ?? ''));
        $form_fecha_retorno = trim((string)($input['fecha_retorno'] ?? ''));
        $form_direccion_evento = trim((string)($input['direccion_evento'] ?? ''));
        $form_nota = trim((string)($input['nota'] ?? ''));

        // ACCIÓN: CREAR RESERVA

        if ($submitted) {
            $actionResult = $this->save($input, [], $actor);
            if ($actionResult->destination !== null || $actionResult->errorBody !== null) return $actionResult;
            extract($actionResult->data, EXTR_OVERWRITE);
        }

        // LISTADO Y FILTROS

        extract($this->repository->listing($query), EXTR_OVERWRITE);
        $clients = $this->repository->get_active_clients();
        $ok  = ($feedback['ok'] ?? null);
        $err = ($feedback['err'] ?? null);
        return $result->withData(compact('error', 'hoy', 'form_cliente_id', 'form_fecha_salida', 'form_fecha_evento', 'form_fecha_retorno', 'form_direccion_evento', 'form_nota', 'q', 'estado_filtro', 'estados_validos', 'rows', 'clients', 'ok', 'err'));
    }
}
