<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$root = dirname(__DIR__);
$count = 0;
$check = static function (bool $condition, string $message) use (&$count): void {
    if (!$condition) throw new RuntimeException($message);
    $count++;
};
$routes = require $root . '/app/config/routes.php';
foreach ($routes as $route => $controller) {
    $check(class_exists($controller), 'Controlador inexistente: ' . $controller);
    $source = file_get_contents($root . '/public/' . $route . '.php');
    $check(substr_count($source, "\n") <= 8, 'La entrada pública contiene lógica: ' . $route);
}
foreach (['Controllers', 'Services', 'views'] as $folder) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/' . $folder));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') continue;
        $source = file_get_contents($file->getPathname());
        $check(!preg_match('/\b(SELECT\s+.+?\s+FROM|INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM)\b/is', $source), 'SQL fuera de repositorios: ' . $file);
        if ($folder === 'Services') {
            $check(!preg_match('/\$_(GET|POST|SESSION|SERVER)\b|\b(header|http_response_code|csrf_validate|require_permission)\s*\(|\bexit\s*;/', $source), 'Dependencia HTTP en servicio: ' . $file);
        }
        if ($folder === 'views') {
            $check(!preg_match('/\b(?:db|prepare|query)\s*\(|(?<![-\w])(?:onclick|onsubmit|style)=|<script>/', $source), 'Lógica o diseño incrustado en vista: ' . $file);
            preg_match_all('~(?:href|src)="(assets/[^"?]+)~', $source, $assets);
            foreach ($assets[1] as $asset) $check(is_file($root . '/public/' . $asset), 'Recurso inexistente: ' . $asset);
        }
    }
}
echo "OK: {$count} comprobaciones de estructura, rutas y recursos.\n";
