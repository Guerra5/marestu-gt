<?php
declare(strict_types=1);

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  // Opcional para desarrollo local; Docker conserva prioridad por entorno.
  $localFile = __DIR__ . '/db.local.php';
  $local = is_file($localFile) ? require $localFile : [];

  $host = getenv('DB_HOST') ?: ($local['host'] ?? 'db');
  $dbname = getenv('DB_NAME') ?: ($local['database'] ?? 'marestu');
  $user = getenv('DB_USER') ?: ($local['username'] ?? 'administrador');
  $environmentPass = getenv('DB_PASS');
  $pass = $environmentPass !== false ? $environmentPass : (string)($local['password'] ?? '');
  $port = (int)(getenv('DB_PORT') ?: ($local['port'] ?? 3306));
  $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
  $opts = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];

  $pdo = new PDO($dsn, $user, $pass, $opts);
  return $pdo;
}
