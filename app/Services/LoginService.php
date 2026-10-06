<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\LoginRepository;

final class LoginService {
    use Login\Actions\SaveAction;

    public function __construct(private readonly LoginRepository $repository) {}

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();
        $error = null;
        if ($submitted) {
            $actionResult = $this->save($input, [], $actor);
            if ($actionResult->destination !== null || $actionResult->errorBody !== null) return $actionResult;
            extract($actionResult->data, EXTR_OVERWRITE);
        }
        return $result->withData(compact('error'));
    }
}
