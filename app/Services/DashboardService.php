<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\DashboardRepository;

final class DashboardService {

    public function __construct(private readonly DashboardRepository $repository) {}

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();
        extract($this->repository->report($query), EXTR_OVERWRITE);
        return $result->withData(compact('today', 'in7', 'in14', 'kpi_reservas_hoy', 'kpi_devoluciones_hoy', 'kpi_proximas_7d', 'kpi_entregas_pend', 'kpi_devol_pend', 'kpi_items_activos', 'kpi_stock_activo', 'stock_criticos', 'stock_bajos', 'prox_reservas', 'top_art', 'chart_estado_labels', 'chart_estado_values', 'chart_top_labels', 'chart_top_values'));
    }
}
