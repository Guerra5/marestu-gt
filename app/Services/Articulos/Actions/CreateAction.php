<?php
declare(strict_types=1);
namespace Marestu\Services\Articulos\Actions;

use Throwable;
use Marestu\Services\ActionResult;

trait CreateAction {
    public function create(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $nombre = $this->normalize_text((string)($input['nombre'] ?? ''));
        $categoria_id = (int)($input['categoria_id'] ?? 0);
        $unidad = $this->normalize_text((string)($input['unidad'] ?? 'pza'));
        $ubicacion = $this->normalize_text((string)($input['ubicacion'] ?? ''));
        $observaciones = trim((string)($input['observaciones'] ?? ''));
        $precio_unitario = (float)($input['precio_unitario'] ?? 0);
        if ($precio_unitario < 0) $precio_unitario = 0;
        $cantidad_total = (int)($input['cantidad_total'] ?? 0);
        $cantidad_activa = (int)($input['cantidad_activa'] ?? 0);
        // 🔒 Validación backend: categoría debe estar ACTIVA
        $stCat = $this->repository->create_select_categorias([$categoria_id]);
        $catRow = $stCat->fetch();
        if ($nombre === '') {
            $error = "El nombre es obligatorio.";
        } elseif ($categoria_id <= 0) {
            $error = "Seleccioná una categoría.";
        } elseif (!$catRow || (int)$catRow['activo'] !== 1) {
            $error = "No se puede usar una categoría desactivada.";
        } elseif ($unidad === '') {
            $error = "La unidad es obligatoria.";
        } elseif ($cantidad_total < 0 || $cantidad_activa < 0) {
            $error = "Las cantidades no pueden ser negativas.";
        } elseif ($cantidad_activa > $cantidad_total) {
            $error = "La cantidad activa no puede ser mayor que la total.";
        } elseif ($precio_unitario < 0) {
            $error = "El precio no puede ser negativo.";
        } else {
            $codigo = $this->repository->next_art_code();
            $this->repository->beginTransaction();
            try {
                $ins = $this->repository->create_insert_into_articulos([
                        $codigo, $nombre, $categoria_id, $unidad,
                        $precio_unitario,
                        $cantidad_total, $cantidad_activa,
                        ($ubicacion !== '' ? $ubicacion : null),
                        ($observaciones !== '' ? $observaciones : null),
                ]);
                $articulo_id = (int)$this->repository->lastInsertId();
                // Kardex: si entra stock activo inicial, registrar ENTRADA
                if ($cantidad_activa > 0) {
                    $nota = "Stock inicial. Total={$cantidad_total}, Activo={$cantidad_activa}.";
                    $mov = $this->repository->create_insert_into_movimientos_inventario([$articulo_id, $cantidad_activa, $nota, (int)$actor['id']]);
                }
                $this->repository->commit();
                $result->message('ok', "Artículo creado: {$codigo}");
                return $result->redirect('articulos.php');
            } catch (Throwable $e) {
                $this->repository->rollBack();
                $error = $e->getMessage();
            }
        }
        return $result->withData(['error' => $error]);
    }
}
