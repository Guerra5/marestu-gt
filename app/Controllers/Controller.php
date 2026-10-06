<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Response;
use Marestu\Http\View;
use Marestu\Services\ActionResult;

abstract class Controller {
    protected function finish(ActionResult $result, string $template, bool $layout = true): Response {
        foreach ($result->messages as $key => $message) flash_set($key, $message);
        if ($result->authenticatedUser !== null) {
            session_regenerate_id(true);
            $_SESSION['user'] = $result->authenticatedUser;
        }
        if ($result->destination !== null) return Response::redirect($result->destination);
        if ($result->errorBody !== null) return new Response($result->errorBody, $result->status);
        $data = $result->data;
        if ($layout) $data['alertas'] = (new \Marestu\Repositories\NavigationRepository(db()))->alerts();
        return new Response(View::render($template, $data));
    }

    protected function feedback(): array {
        $feedback = [];
        foreach (['ok', 'err', 'temp_pass'] as $key) $feedback[$key] = flash_get($key);
        return $feedback;
    }
}
