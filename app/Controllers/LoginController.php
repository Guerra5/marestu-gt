<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\LoginService;
use Marestu\Repositories\LoginRepository;

final class LoginController extends Controller {
    public function __invoke(Request $request): Response {
        if (is_logged_in()) {
            return Response::redirect('index.php');
        }
        if ($request->isPost() && !csrf_validate($request->input['csrf'] ?? null)) {
            return new Response('Token inválido. Recargá la página.', 403);
        }
        $service = new LoginService(new LoginRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'login/index', false);
    }
}
