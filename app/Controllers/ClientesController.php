<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\ClientesService;
use Marestu\Repositories\ClientesRepository;

final class ClientesController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        require_permission('clientes.ver');
        if ($request->isPost() || (int)($request->query['edit'] ?? 0) > 0) {
            require_permission('clientes.gestionar');
        }
        if ($request->isPost() && !csrf_validate($request->input['csrf'] ?? null)) {
            return new Response('Token inválido. Recargá la página.', 403);
        }
        $service = new ClientesService(new ClientesRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'clientes/index', true);
    }
}
