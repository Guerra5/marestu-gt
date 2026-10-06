<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/permissions.php';

function app_config(): array {
  static $cfg = null;
  if ($cfg !== null) return $cfg;
  $cfg = require __DIR__ . '/../config/app.php';
  return $cfg;
}

function start_app_session(): void {
  $cfg = app_config();
  if (session_status() === PHP_SESSION_NONE) {
    session_name($cfg['session_name'] ?? 'app_session');
    session_start();
  }
}

function current_user(): ?array {
  if (empty($_SESSION['user'])) return null;
  return is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function is_logged_in(): bool {
  return current_user() !== null;
}

function require_login(): void {
  if (!is_logged_in()) {
    header('Location: login.php');
    exit;
  }
}

function is_admin(): bool {
  $u = current_user();
  return $u && ($u['rol'] ?? '') === 'ADMIN';
}

function logout(): void {
  unset($_SESSION['user']);
  session_regenerate_id(true);
}
