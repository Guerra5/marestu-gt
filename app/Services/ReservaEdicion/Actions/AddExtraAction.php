<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaEdicion\Actions;

use Marestu\Services\ActionResult;

trait AddExtraAction {
    public function add_extra(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        if ($res['estado'] !== 'BORRADOR') {
            $result->message(
                'err',
                "Solo podés modificar extras en estado BORRADOR."
            );
            return $result->redirect("reserva_editar.php?id={$id}");
        }
        $descripcion = $this->normalize_text(
            (string)($input['descripcion'] ?? '')
        );
        $proveedor = $this->normalize_text(
            (string)($input['proveedor'] ?? '')
        );
        $cantidad = (int)($input['cantidad'] ?? 0);
        $precio = (float)($input['precio_unitario'] ?? 0);
        if ($descripcion === '') {
            $error = "La descripción del extra es obligatoria.";
        } elseif ($cantidad <= 0) {
            $error = "Cantidad inválida.";
        } elseif ($precio < 0) {
            $error = "El precio no puede ser negativo.";
        } else {
            $ins = $this->repository->add_extra_insert_into_reserva_extras([
                    $id,
                    $descripcion,
                    ($proveedor !== '' ? $proveedor : null),
                    $cantidad,
                    $precio
            ]);
            $result->message('ok', "Extra agregado.");
            return $result->redirect("reserva_editar.php?id={$id}");
        }
        return $result->withData(['error' => $error]);
    }
}
