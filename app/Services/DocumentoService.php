<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\DocumentoRepository;

final class DocumentoService {
    public function __construct(private readonly DocumentoRepository $repository) {}

    public function data(int $id, string $type): ActionResult {
        $result = new ActionResult();
        $res = $this->repository->reservation($id);
        if (!$res) return $result->stop('Reserva no encontrada.', 404);
        return $result->withData([
                'res' => $res,
                'rows' => $this->repository->items($id),
                'ext_rows' => $this->repository->extras($id),
                'type' => $type,
        ]);
    }
}
