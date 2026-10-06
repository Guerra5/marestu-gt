<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\ReservaOperacionService;
use Marestu\Repositories\ReservaOperacionRepository;

final class ReservaOperacionController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        require_permission('reservas.operar');
        if ($request->isPost()) {
            require_permission(in_array((string)($request->input['action'] ?? ''), ['entregar', 'devolver'], true) ? 'reservas.operar' : 'reservas.gestionar');
        }
        if ($request->isPost() && !csrf_validate($request->input['csrf'] ?? null)) {
            return new Response('Token inválido. Recargá la página.', 403);
        }
        $service = new ReservaOperacionService(new ReservaOperacionRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'reserva_operacion/index', true);
    }
}
