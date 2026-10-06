<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\ReservaEdicionRepository;

final class ReservaEdicionService {
    use ReservaEdicion\Actions\DireccionEventoAction;
    use ReservaEdicion\Actions\AddItemAction;
    use ReservaEdicion\Actions\RemoveItemAction;
    use ReservaEdicion\Actions\AddExtraAction;
    use ReservaEdicion\Actions\RemoveExtraAction;
    use ReservaEdicion\Actions\SetStatusAction;

    public function __construct(private readonly ReservaEdicionRepository $repository) {}

    private function normalize_text(string $s): string {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s ?? '';
    }

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        if (!$submitted) return $this->executeRequest($query, $input, $actor, false, $feedback);
        try {
            return $this->repository->withReservationTransaction(
                (int)($query['id'] ?? 0), (int)($input['articulo_id'] ?? 0),
                fn () => $this->executeRequest($query, $input, $actor, true, $feedback)
            );
        } catch (\Throwable $e) {
            error_log('Reserva: ' . $e->getMessage());
            $result = new ActionResult();
            $result->message('err', 'No se pudo completar la operación. Recargá la reserva e intentá nuevamente.');
            return $result->redirect('reserva_editar.php?id=' . (int)($query['id'] ?? 0));
        }
    }

    private function executeRequest(array $query, array $input, array $actor, bool $submitted, array $feedback): ActionResult {
        $result = new ActionResult();

        $id = (int)($query['id'] ?? 0);
        if ($id <= 0) {
            return $result->stop("ID inválido.");
        }
        $res = $this->repository->get_reserva($id);
        if (!$res) {
            return $result->stop("Reserva no encontrada.");
        }
        $items = $this->repository->reserva_items($id);
        $extras = $this->repository->reserva_extras($id);
        $arts = $this->repository->get_articulos_activos();
        /*
         * Generar listado único de categorías utilizando los artículos
         * que ya fueron consultados.
         */
        $categorias = [];
        foreach ($arts as $articulo) {
            $categoria_id = (int)$articulo['categoria_id'];
            $categorias[$categoria_id] = (string)$articulo['categoria'];
        }
        $error = null;

        // POST ACTIONS

        if ($submitted) {
            $action = (string)($input['action'] ?? '');

            $actionResult = match ($action) {
                'direccion_evento' => $this->direccion_evento($input, compact('id', 'res'), $actor),
                'add_item' => $this->add_item($input, compact('id', 'res'), $actor),
                'remove_item' => $this->remove_item($input, compact('id', 'res'), $actor),
                'add_extra' => $this->add_extra($input, compact('id', 'res'), $actor),
                'remove_extra' => $this->remove_extra($input, compact('id', 'res'), $actor),
                'set_status' => $this->set_status($input, compact('id', 'res'), $actor),
                default => (new ActionResult())->stop('Acción desconocida.', 400),
            };
            if ($actionResult->destination !== null || $actionResult->errorBody !== null) return $actionResult;
            extract($actionResult->data, EXTR_OVERWRITE);
        }

        // RECARGAR DATOS PARA MOSTRAR

        $res = $this->repository->get_reserva($id);
        $items = $this->repository->reserva_items($id);
        $extras = $this->repository->reserva_extras($id);
        $ok = ($feedback['ok'] ?? null);
        $err = ($feedback['err'] ?? null);

        // TOTALES

        $total_inv = 0.0;
        foreach ($items as $it) {
            $total_inv += (
                (float)$it['precio_unitario']
            ) * (
                (int)$it['cantidad']
            );
        }
        $total_ext = 0.0;
        foreach ($extras as $ex) {
            $total_ext += (float)$ex['subtotal'];
        }
        $total_gral = $total_inv + $total_ext;
        return $result->withData(compact('id', 'res', 'items', 'extras', 'arts', 'categorias', 'error', 'ok', 'err', 'total_inv', 'total_ext', 'total_gral'));
    }
}
