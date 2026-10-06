-- Ejecutar sobre la base existente ANTES de publicar el código actualizado.
-- MariaDB: puede ejecutarse nuevamente sin borrar ni modificar reservas.
ALTER TABLE reservas
  ADD COLUMN IF NOT EXISTS direccion_evento TEXT NULL;
