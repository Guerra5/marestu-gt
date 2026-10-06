# MARESTU

Sistema de clientes, inventario, reservas, operación diaria y Kardex.

Este repositorio contiene la aplicación: `app/` y `public/` están en la raíz. Si la instalación Docker monta `./src:/var/www/html`, esta raíz corresponde a la carpeta `src` del servidor; no crear otra carpeta `src` dentro de ella.

## Requisitos y configuración

- PHP 8.1 o superior con PDO MySQL, DOM y mbstring; MariaDB con tablas InnoDB.
- El directorio público del servidor es `public/`.
- Configurar `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS` mediante el entorno del servidor. El código no contiene una contraseña de base de datos.
- Para pruebas locales, copiar `app/config/db.local.example.php` a `app/config/db.local.php` y usar una base separada. Las variables del entorno tienen prioridad.
- Instalar las dependencias de PDF desde el lock:

```sh
composer install --no-dev --prefer-dist --no-interaction
composer check-platform-reqs --no-dev
```

`vendor/`, configuraciones locales, credenciales y respaldos no se versionan. El instalador público antiguo se retiró: la actualización utiliza la base y los usuarios existentes.

## Organización

- `app/Controllers`: acceso, permisos y respuesta HTTP.
- `app/Services`: reglas de negocio y acciones.
- `app/Repositories`: SQL, transacciones y bloqueos.
- `app/views`: plantillas.
- `public/assets`: CSS y JavaScript.
- `migrations`: cambios incrementales de base de datos.
- `tests`: pruebas automáticas y QA en bases locales aisladas.

Las rutas públicas anteriores se conservan. El operador consulta clientes y reservas, opera entregas/devoluciones/daños/pérdidas, gestiona categorías/artículos e inventario y consulta Kardex. La administración de clientes, reservas y usuarios permanece restringida. La dirección del evento se guarda en cada reserva, independientemente de la del cliente.

## Pruebas

```sh
php tests/run.php
node tests/assets.js
```

Para QA con MariaDB local, configurar primero `app/config/db.local.php` para una base de prueba cuyo esquema ya exista y activar MySQL:

```sh
php tests/qa-http.php
php tests/qa-concurrency.php
php tests/qa-business-edges.php
php tests/qa-lock-timeout.php
```

La prueba HTTP crea otra base `marestu_qa_*`, copia únicamente el esquema y utiliza datos ficticios. Los demás scripts usan esa última base QA. Nunca apuntar las pruebas a producción. Los artefactos se guardan en `backups/qa/` y no se suben al repositorio.

El QA original de la versión corregida pasó 123 comprobaciones funcionales (HTTP/SQL, Chrome, concurrencia y PDF) y 380 comprobaciones de estructura, permisos y servicios. El resultado local no certifica la infraestructura del cliente. Ver [el procedimiento de actualización](docs/actualizacion.md).
