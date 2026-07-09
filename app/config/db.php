<?php
declare(strict_types=1);

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $host = '127.0.0.1';
  $dbname = 'marestu';
  $user = 'administrador';
  $pass = 'e11c2m3'; // en XAMPP casi siempre vacío

  $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
  $opts = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];

  $pdo = new PDO($dsn, $user, $pass, $opts);
  return $pdo;
}
