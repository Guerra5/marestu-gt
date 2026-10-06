<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaOperacion\Actions;

use Throwable;
use RuntimeException;
use Marestu\Services\ActionResult;

trait EntregaAdicionalAction {
    public function entrega_adicional(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        $estado = (string)$res['estado'];
        if (!(($actor['rol'] ?? '') === 'ADMIN')) {
            $result->message(
                'err',
                "Solo ADMIN puede agregar artículos al pedido confirmado."
            );
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        if (
            !in_array(
                $estado,
                ['CONFIRMADA', 'ENTREGADA'],
                true
            )
        ) {
            $result->message(
                'err',
                "Solo se puede agregar al pedido cuando la reserva está CONFIRMADA o ENTREGADA."
            );
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        $tipo = (string)($input['tipo'] ?? '');
        if (!in_array($tipo, ['INV', 'EXT'], true)) {
            $error = "Tipo de artículo adicional inválido.";
        } else {
            $this->repository->beginTransaction();
            $this->repository->entrega_adicional_exec();
            try {

                // INVENTARIO

                if ($tipo === 'INV') {
                    $articulo_id =
                    (int)($input['articulo_id'] ?? 0);
                    $cant =
                    (int)($input['cantidad_inv'] ?? 0);
                    if ($articulo_id <= 0 || $cant <= 0) {
                        throw new RuntimeException(
                            "Seleccioná un artículo y una cantidad válida."
                        );
                    }
                    // Bloquear artículo y validar stock
                    $stLock = $this->repository->entrega_adicional_select_articulos([$articulo_id]);
                    $artRow = $stLock->fetch();
                    if (!$artRow) {
                        throw new RuntimeException(
                            "Artículo no encontrado o inactivo."
                        );
                    }
                    // Buscar si ya existe en la reserva
                    $stx = $this->repository->entrega_adicional_select_reserva_detalle([
                            $id,
                            $articulo_id
                    ]);
                    $ex = $stx->fetch();
                    $disp = $this->repository->availableForReservation($articulo_id, (string)$res['fecha_salida'], (string)$res['fecha_retorno'], $id);
                    $yaPedido = $ex ? (int)$ex['cantidad'] : 0;
                    if ($yaPedido + $cant > $disp) {
                        throw new RuntimeException('Stock insuficiente. Disponible para agregar: ' . max(0, $disp - $yaPedido) . '.');
                    }
                    if ($ex) {
                        $det_id = (int)$ex['id'];
                        $up = $this->repository->entrega_adicional_update_reserva_detalle([
                                $cant,
                                $det_id,
                                $id
                        ]);
                    } else {
                        $ins = $this->repository->entrega_adicional_insert_into_reserva_detalle([
                                $id,
                                $articulo_id,
                                $cant
                        ]);
                    }
                }

                // EXTRA / SERVICIO EXTERNO

                if ($tipo === 'EXT') {
                    $desc = trim(
                        (string)($input['descripcion'] ?? '')
                    );
                    $prov = trim(
                        (string)($input['proveedor'] ?? '')
                    );
                    $cant =
                    (int)($input['cantidad_ext'] ?? 0);
                    $precio =
                    (float)($input['precio_unitario'] ?? 0);
                    if ($desc === '' || $cant <= 0) {
                        throw new RuntimeException(
                            "Descripción y cantidad son obligatorias."
                        );
                    }
                    if ($precio < 0) {
                        throw new RuntimeException(
                            "El precio no puede ser negativo."
                        );
                    }
                    $sub = $cant * $precio;
                    $ins = $this->repository->entrega_adicional_insert_into_reserva_extras([
                            $id,
                            $desc,
                            ($prov !== '' ? $prov : null),
                            $cant,
                            $precio,
                            $sub
                    ]);
                }
                // Si ya estaba ENTREGADA, vuelve a CONFIRMADA porque
                // ahora existe al menos un artículo pendiente de entrega.
                if ($estado === 'ENTREGADA') {
                    $this->repository->set_estado($id, 'CONFIRMADA');
                }
                $this->repository->commit();
                $result->message(
                    'ok',
                    "Artículo agregado al pedido. Quedó pendiente de entrega."
                );
                return $result->redirect("reserva_operacion.php?id={$id}");
            } catch (Throwable $e) {
                if ($this->repository->inTransaction()) {
                    $this->repository->rollBack();
                }
                $error = $e->getMessage();
            }
        }
        return $result->withData(['error' => $error]);
    }
}
