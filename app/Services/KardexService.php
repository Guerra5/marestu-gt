<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\KardexRepository;

final class KardexService {

    public function __construct(private readonly KardexRepository $repository) {}

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();
        extract($this->repository->report($query), EXTR_OVERWRITE);
        $paginationQuery = $query;
        return $result->withData(compact('tipos_validos', 'categoria_id', 'articulo_id', 'tipo', 'desde', 'hasta', 'q', 'page', 'per_page', 'offset', 'categorias', 'articulos', 'total_rows', 'total_pages', 'rows', 'entradas', 'salidas', 'neto', 'paginationQuery'));
    }
}
