<?php
declare(strict_types=1);
namespace Marestu\Http;

final class Application {
    public static function run(string $route): void {
        $routes = require dirname(__DIR__) . '/config/routes.php';
        if (!isset($routes[$route])) {
            (new Response('Página no encontrada.', 404))->send();
            return;
        }
        start_app_session();
        $controller = new $routes[$route]();
        $controller(Request::fromGlobals())->send();
    }
}
