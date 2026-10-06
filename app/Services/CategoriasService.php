<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\CategoriasRepository;

final class CategoriasService {
    use Categorias\Actions\CreateAction;
    use Categorias\Actions\UpdateAction;
    use Categorias\Actions\ToggleAction;

    public function __construct(private readonly CategoriasRepository $repository) {}

    private function normalize_cat(string $s): string {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s ?? '';
    }

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();
        $error = null;
        // Mostrar modal/estado de edición por GET
        $edit_id = (int)($query['edit'] ?? 0);
        $edit_row = null;
        if ($edit_id > 0) {
            $st = $this->repository->load_select_categorias([$edit_id]);
            $edit_row = $st->fetch();
            if (!$edit_row) {
                $result->message('err', "Categoría no encontrada.");
                return $result->redirect('categorias.php');
            }
        }

        if ($submitted) {
            $action = (string)($input['action'] ?? '');

            $actionResult = match ($action) {
                'create' => $this->create($input, [], $actor),
                'update' => $this->update($input, [], $actor),
                'toggle' => $this->toggle($input, [], $actor),
                default => (new ActionResult())->stop('Acción desconocida.', 400),
            };
            if ($actionResult->destination !== null || $actionResult->errorBody !== null) return $actionResult;
            extract($actionResult->data, EXTR_OVERWRITE);
        }

        extract($this->repository->listing($query), EXTR_OVERWRITE);
        $ok  = ($feedback['ok'] ?? null);
        $err = ($feedback['err'] ?? null);
        return $result->withData(compact('error', 'edit_row', 'q', 'rows', 'ok', 'err'));
    }
}
