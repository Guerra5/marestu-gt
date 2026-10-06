<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\CotizacionService;
use Marestu\Repositories\CotizacionRepository;

final class CotizacionController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        $service = new CotizacionService(new CotizacionRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'reserva_cotizacion/index', false);
    }
}
