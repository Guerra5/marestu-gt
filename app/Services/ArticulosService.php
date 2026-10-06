<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\ArticulosRepository;

final class ArticulosService {
    use Articulos\Actions\CreateAction;
    use Articulos\Actions\UpdateAction;
    use Articulos\Actions\StockAction;
    use Articulos\Actions\ToggleAction;

    public function __construct(private readonly ArticulosRepository $repository) {}

    private function normalize_text(string $s): string {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s ?? '';
    }

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();

        $error = null;

        $cats_all = $this->repository->get_categories_all();
        $cats_active = $this->repository->get_categories_active(); // para CREAR

        $edit_id = (int)($query['edit'] ?? 0);
        $edit_row = null;
        if ($edit_id > 0) {
            $st = $this->repository->load_select_articulos([$edit_id]);
            $edit_row = $st->fetch();
            if (!$edit_row) {
                $result->message('err', "Artículo no encontrado.");
                return $result->redirect('articulos.php');
            }
        }

        if ($submitted) {
            $action = (string)($input['action'] ?? '');

            $actionResult = match ($action) {
                'create' => $this->create($input, [], $actor),
                'update' => $this->update($input, [], $actor),
                'stock' => $this->stock($input, [], $actor),
                'toggle' => $this->toggle($input, [], $actor),
                default => (new ActionResult())->stop('Acción desconocida.', 400),
            };
            if ($actionResult->destination !== null || $actionResult->errorBody !== null) return $actionResult;
            extract($actionResult->data, EXTR_OVERWRITE);
        }

        extract($this->repository->listing($query), EXTR_OVERWRITE);
        $ok  = ($feedback['ok'] ?? null);
        $err = ($feedback['err'] ?? null);
        return $result->withData(compact('error', 'cats_all', 'cats_active', 'edit_row', 'q', 'cat', 'estado_f', 'rows', 'ok', 'err'));
    }
}
