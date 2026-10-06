<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\ArticulosService;
use Marestu\Repositories\ArticulosRepository;

final class ArticulosController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        require_permission('inventario.gestionar');
        if ($request->isPost() && !csrf_validate($request->input['csrf'] ?? null)) {
            return new Response('Token inválido. Recargá la página.', 403);
        }
        $service = new ArticulosService(new ArticulosRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'articulos/index', true);
    }
}
