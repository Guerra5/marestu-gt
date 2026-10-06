<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\KardexService;
use Marestu\Repositories\KardexRepository;

final class KardexController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        require_permission('kardex.ver');
        $service = new KardexService(new KardexRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'kardex/index', true);
    }
}
