<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\CalendarioService;
use Marestu\Repositories\CalendarioRepository;

final class CalendarioController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        $service = new CalendarioService(new CalendarioRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'calendario/index', true);
    }
}
