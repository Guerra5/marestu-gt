# Actualizar una instalación existente

Los cambios preservan las URLs y la base del cliente. No copiar un respaldo SQL completo encima de la base activa.

1. Preparar la versión en una carpeta de prueba con el mismo entorno PHP/MariaDB del cliente. El repositorio contiene `app/` y `public/` directamente, no una carpeta `src/` adicional.
2. Instalar `vendor` con `composer install --no-dev --prefer-dist --no-interaction`, utilizando `composer.lock`. Ejecutar `composer check-platform-reqs --no-dev` y las pruebas de `tests/run.php`.
3. Mantener la configuración de producción. La contraseña se obtiene de `DB_PASS` o del archivo privado `app/config/db.local.php`; ya no existe una contraseña de respaldo en el código. No reemplazar esa configuración por la de XAMPP.
4. Respaldar los archivos actuales, incluido `vendor`, y la base activa fuera del directorio público.
5. Aplicar `migrations/20260921_direccion_evento.sql` si la columna aún no existe. Es aditiva y repetible en MariaDB. Las correcciones de concurrencia no necesitan otra migración.
6. Sustituir juntos `app`, `public` y las dependencias, durante una pausa de operaciones. Retirar `public/instalar.php` si se despliega copiando archivos: ya no forma parte de la versión. Mantener el servidor apuntando a `public`. Recargar PHP-FPM/OPcache según la configuración existente.
7. Verificar ADMIN/OPERADOR, una reserva histórica, su dirección y los documentos PDF. Completar el recorrido funcional en la copia de prueba antes de habilitar la nueva versión.

Para revertir, restaurar la versión anterior completa y su configuración, manteniendo la base activa y la columna adicional de dirección. No restaurar un respaldo antiguo de la base sobre operaciones nuevas.

## Correcciones incluidas

- Lectura, validación y escritura de operaciones bajo un bloqueo de la reserva y una transacción. Se evita exceder los pendientes al operar simultáneamente.
- Bloqueo compartido por artículo, con orden estable y lecturas READ COMMITTED, para confirmar/agregar/editar sin comprometer más disponibilidad que la existente.
- Espera máxima de bloqueo de 5 segundos, con error recuperable y sin operaciones parciales.
- Cálculo corregido al reemplazar cantidades de un borrador.
- Cancelación rechazada si quedan artículos o extras entregados pendientes de devolución.
- Dependencias PDF fijadas por Composer. Precios NULL del detalle usan el precio del artículo; se conserva el precio del detalle cuando está definido. La fecha del evento usa su campo correspondiente y la dirección del evento aparece en los documentos.

Dos solicitudes parciales independientes que aún caben en lo pendiente siguen siendo operaciones válidas. Estos bloqueos protegen cantidades y estados; no introducen un mecanismo nuevo de idempotencia.

Subir una rama o fusionar un PR en GitHub no sustituye este procedimiento. No se encontró configuración de GitHub Actions ni webhooks en el repositorio al preparar esta versión; un `git pull`, una tarea programada u otra automatización externa del servidor pueden existir y deben comprobarse antes de actualizar `main` en producción.
