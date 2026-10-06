<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . ''));
$count = 0;
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;
    token_get_all(file_get_contents($file->getPathname()), TOKEN_PARSE);
    $count++;
}
echo "OK: sintaxis de {$count} archivos PHP.\n";
foreach (['structure.php', 'permissions.php', 'services.php'] as $test) {
    $process = proc_open([PHP_BINARY, '-n', __DIR__ . '/' . $test], [1 => STDOUT, 2 => STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) exit(1);
}
