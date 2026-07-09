<?php
declare(strict_types=1);

function flash_set(string $key, string $msg): void {
  $_SESSION['flash'][$key] = $msg;
}

function flash_get(string $key): ?string {
  if (!isset($_SESSION['flash'][$key])) return null;
  $msg = (string)$_SESSION['flash'][$key];
  unset($_SESSION['flash'][$key]);
  return $msg;
}
