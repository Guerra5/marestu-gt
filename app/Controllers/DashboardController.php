<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Services\DashboardService;
use Marestu\Repositories\DashboardRepository;

final class DashboardController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        $service = new DashboardService(new DashboardRepository(db()));
        $result = $service->execute($request->query, $request->input, current_user() ?? [], $request->isPost(), $this->feedback());
        return $this->finish($result, 'index/index', true);
    }
}
