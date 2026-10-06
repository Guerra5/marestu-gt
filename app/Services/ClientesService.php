<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\ClientesRepository;

final class ClientesService {
    use Clientes\Actions\CreateAction;
    use Clientes\Actions\UpdateAction;
    use Clientes\Actions\ToggleAction;

    public function __construct(private readonly ClientesRepository $repository) {}

    private function norm(string $s): string {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s ?? '';
    }

    private function norm_email(?string $s): ?string {
        $s = trim((string)$s);
        if ($s === '') return null;
        $s = strtolower($s);
        return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : null;
    }

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();
        $error = null;

        $edit_id = (int)($query['edit'] ?? 0);
        $edit_row = null;
        if ($edit_id > 0) {
            $st = $this->repository->load_select_clientes([$edit_id]);
            $edit_row = $st->fetch();
            if (!$edit_row) {
                $result->message('err', "Cliente no encontrado.");
                return $result->redirect('clientes.php');
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
        return $result->withData(compact('error', 'edit_row', 'q', 'estado_f', 'rows', 'ok', 'err'));
    }
}
