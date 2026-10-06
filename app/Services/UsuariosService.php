<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\UsuariosRepository;

final class UsuariosService {
    use Usuarios\Actions\CreateAction;
    use Usuarios\Actions\ToggleAction;
    use Usuarios\Actions\ResetAction;

    public function __construct(private readonly UsuariosRepository $repository) {}

    private function gen_temp_password(int $len = 10): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
        $out = '';
        for ($i=0; $i<$len; $i++) {
            $out .= $chars[random_int(0, strlen($chars)-1)];
        }
        return $out;
    }

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();
        $error = null;
        $temp_password_shown = ($feedback['temp_pass'] ?? null); // se muestra una sola vez

        if ($submitted) {
            $action = (string)($input['action'] ?? '');

            $actionResult = match ($action) {
                'create' => $this->create($input, [], $actor),
                'toggle' => $this->toggle($input, [], $actor),
                'reset' => $this->reset($input, [], $actor),
                default => (new ActionResult())->stop('Acción desconocida.', 400),
            };
            if ($actionResult->destination !== null || $actionResult->errorBody !== null) return $actionResult;
            extract($actionResult->data, EXTR_OVERWRITE);
        }

        extract($this->repository->listing($query), EXTR_OVERWRITE);
        $ok  = ($feedback['ok'] ?? null);
        $err = ($feedback['err'] ?? null);
        return $result->withData(compact('error', 'temp_password_shown', 'rows', 'q', 'ok', 'err'));
    }
}
