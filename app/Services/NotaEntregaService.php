<?php
declare(strict_types=1);
namespace Marestu\Services;

use DateTime;
use Marestu\Repositories\NotaEntregaRepository;

final class NotaEntregaService {

    public function __construct(private readonly NotaEntregaRepository $repository) {}

    private function date_add_days(string $ymd, int $days): string {
        $dt = new DateTime($ymd);
        $dt->modify(($days >= 0 ? '+' : '') . $days . ' day');
        return $dt->format('Y-m-d');
    }

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

        $fecha_entrega = (string)$res['fecha_salida'];
        $fecha_evento  = $this->date_add_days($fecha_entrega, 1);
        $fecha_desmont = (string)$res['fecha_retorno'];

        $nota_reserva = trim((string)($res['nota'] ?? ''));
        return $result->withData(compact('id', 'res', 'items', 'extras', 'empresa_email', 'empresa_tel', 'fecha_entrega', 'fecha_evento', 'fecha_desmont', 'nota_reserva'));
    }
}
