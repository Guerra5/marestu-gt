<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;

final class LogoutController extends Controller {
    public function __invoke(Request $request): Response {
        logout();
        return Response::redirect('login.php');
    }
}
