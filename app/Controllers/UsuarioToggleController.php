<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Repositories\UsuariosRepository;
use Marestu\Services\UsuariosService;

final class UsuarioToggleController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        if (!is_admin()) return new Response('No autorizado.', 403);
        if (!$request->isPost()) return new Response('', 405, ['Allow' => 'POST']);
        if (!csrf_validate($request->input['csrf'] ?? null)) return new Response('Token inválido. Recargá la página.', 403);
        $service = new UsuariosService(new UsuariosRepository(db()));
        return $this->finish($service->toggle($request->input, [], current_user()), 'usuarios/index');
    }
}
