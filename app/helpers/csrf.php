<?php
declare(strict_types=1);

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
  }
  return (string)$_SESSION['csrf'];
}

function csrf_validate(?string $token): bool {
  if (!$token || empty($_SESSION['csrf'])) return false;
  return hash_equals((string)$_SESSION['csrf'], (string)$token);
}
