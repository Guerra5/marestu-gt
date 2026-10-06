<?php
declare(strict_types=1);
namespace Marestu\Http;

final class View {
    public static function render(string $template, array $data): string {
        $path = dirname(__DIR__) . '/views/' . $template . '.php';
        if (!is_file($path)) throw new \RuntimeException('Vista no encontrada: ' . $template);
        ob_start();
        try {
            // Los datos vienen exclusivamente de servicios, no de parámetros HTTP.
            extract($data, EXTR_SKIP);
            $returned = require $path;
            $output = (string)ob_get_clean();
            return is_string($returned) ? $returned : $output;
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
