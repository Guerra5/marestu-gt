<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\UsuariosService;
use Marestu\Repositories\UsuariosRepository;

final class UsuariosController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        if (!is_admin()) {
            return new Response('No autorizado.', 403);
        }
        if ($request->isPost() && !csrf_validate($request->input['csrf'] ?? null)) {
            return new Response('Token inválido. Recargá la página.', 403);
        }
        $service = new UsuariosService(new UsuariosRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'usuarios/index', true);
    }
}
