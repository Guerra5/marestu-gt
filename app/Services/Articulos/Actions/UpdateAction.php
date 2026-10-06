<?php
declare(strict_types=1);
namespace Marestu\Services\Articulos\Actions;

use Marestu\Services\ActionResult;

trait UpdateAction {
    public function update(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)($input['id'] ?? 0);
        $nombre = $this->normalize_text((string)($input['nombre'] ?? ''));
        $categoria_id = (int)($input['categoria_id'] ?? 0);
        $unidad = $this->normalize_text((string)($input['unidad'] ?? 'pza'));
        $estado = (string)($input['estado'] ?? 'ACTIVO');
        $ubicacion = $this->normalize_text((string)($input['ubicacion'] ?? ''));
        $observaciones = trim((string)($input['observaciones'] ?? ''));
        $precio_unitario = (float)($input['precio_unitario'] ?? 0);
        if ($precio_unitario < 0) $precio_unitario = 0;
        if ($id <= 0) {
            $result->message('err', "ID inválido.");
            return $result->redirect('articulos.php');
        }
        // Traer categoría actual del artículo (para permitir mantener una categoría inactiva ya asignada)
        $stCur = $this->repository->update_select_articulos([$id]);
        $cur = $stCur->fetch();
        if (!$cur) {
            $result->message('err', "Artículo no encontrado.");
            return $result->redirect('articulos.php');
        }
        $categoria_actual = (int)$cur['categoria_id'];
        // Validar categoría seleccionada
        $stCat = $this->repository->update_select_categorias([$categoria_id]);
        $catRow = $stCat->fetch();
        $catActiva = $catRow && (int)$catRow['activo'] === 1;
        if ($nombre === '') {
            $error = "El nombre es obligatorio.";
        } elseif ($categoria_id <= 0) {
            $error = "Seleccioná una categoría.";
        } elseif (!$catRow) {
            $error = "La categoría no existe.";
        } elseif (!$catActiva && $categoria_id !== $categoria_actual) {
            // 🔒 No permitir cambiar a una categoría inactiva
            $error = "No podés asignar una categoría desactivada (solo se permite mantener la actual por historial).";
        } elseif ($unidad === '') {
            $error = "La unidad es obligatoria.";
        } elseif (!in_array($estado, ['ACTIVO','INACTIVO'], true)) {
            $error = "Estado inválido.";
        } else {
            $up = $this->repository->update_update_articulos([
                    $nombre,
                    $categoria_id,
                    $unidad,
                    $precio_unitario,
                    $estado,
                    ($ubicacion !== '' ? $ubicacion : null),
                    ($observaciones !== '' ? $observaciones : null),
                    $id
            ]);
            $result->message('ok', "Artículo actualizado.");
            return $result->redirect('articulos.php?edit=' . $id);
        }
        return $result->withData(['error' => $error]);
    }
}
