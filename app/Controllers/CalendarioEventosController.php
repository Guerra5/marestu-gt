<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Repositories\CalendarioEventosRepository;
use Marestu\Services\CalendarioEventosService;

final class CalendarioEventosController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        require_permission('reservas.ver');
        $service = new CalendarioEventosService(new CalendarioEventosRepository(db()));
        return Response::json($service->events($request->query));
    }
}
