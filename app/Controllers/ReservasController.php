<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\ReservasService;
use Marestu\Repositories\ReservasRepository;

final class ReservasController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        require_permission('reservas.ver');
        if ($request->isPost()) {
            require_permission('reservas.gestionar');
        }
        if ($request->isPost() && !csrf_validate($request->input['csrf'] ?? null)) {
            return new Response('Token inválido. Recargá la página.', 403);
        }
        $service = new ReservasService(new ReservasRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'reservas/index', true);
    }
}
