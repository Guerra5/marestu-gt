<?php
declare(strict_types=1);

/** Permisos compartidos por las pantallas y sus acciones en el servidor. */
function can(string $permission): bool {
  $role = current_user()['rol'] ?? '';
  $permissions = [
    'ADMIN' => ['clientes.ver', 'clientes.gestionar', 'reservas.ver', 'reservas.gestionar', 'reservas.operar', 'inventario.gestionar', 'kardex.ver'],
    'OPERADOR' => ['clientes.ver', 'reservas.ver', 'reservas.operar', 'inventario.gestionar', 'kardex.ver'],
  ];
  return in_array($permission, $permissions[$role] ?? [], true);
}

function require_permission(string $permission): void {
  if (!can($permission)) {
    http_response_code(403);
    exit('No autorizado para realizar esta acción.');
  }
}
