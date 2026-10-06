<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\NotaEntregaService;
use Marestu\Repositories\NotaEntregaRepository;

final class NotaEntregaController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        $service = new NotaEntregaService(new NotaEntregaRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'reserva_nota_entrega/index', false);
    }
}
