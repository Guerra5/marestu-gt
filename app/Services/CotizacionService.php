<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\CotizacionRepository;

final class CotizacionService {

    public function __construct(private readonly CotizacionRepository $repository) {}

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();
        $id = (int)($query['id'] ?? 0);
        if ($id <= 0) { return $result->stop("ID inválido."); }
        $res = $this->repository->get_reserva($id);
        if (!$res) { return $result->stop("Reserva no encontrada."); }
        $items  = $this->repository->reserva_items($id);
        $extras = $this->repository->reserva_extras($id);

        $empresa_email = 'cori2leon@gmail.com';
        $empresa_tel   = 'Tel. 58599321 - 54123635';
        /// ===== Fechas =====
        $fecha_entrega = (string)$res['fecha_salida'];
        $fecha_evento  = (string)$res['fecha_evento'];
        $fecha_desmont = (string)$res['fecha_retorno'];

        $total = 0.0;
        return $result->withData(compact('id', 'res', 'items', 'extras', 'empresa_email', 'empresa_tel', 'fecha_entrega', 'fecha_evento', 'fecha_desmont', 'total'));
    }
}
