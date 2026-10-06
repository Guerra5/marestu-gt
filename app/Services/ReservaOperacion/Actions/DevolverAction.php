<?php
declare(strict_types=1);
namespace Marestu\Services\ReservaOperacion\Actions;

use Throwable;
use RuntimeException;
use Marestu\Services\ActionResult;

trait DevolverAction {
    public function devolver(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)$context['id'];
        $res = $context['res'];
        $estado = (string)$res['estado'];
        $items = $context['items'];
        $extras = $context['extras'];
        if ($estado !== 'ENTREGADA') {
            $result->message(
                'err',
                "Solo podés registrar devoluciones cuando está ENTREGADA."
            );
            return $result->redirect("reserva_operacion.php?id={$id}");
        }
        $obs_bodega = trim(
            (string)($input['obs_bodega'] ?? '')
        );
        $this->repository->beginTransaction();
        $this->repository->devolver_exec();
        try {
            // Inventario
            foreach ($items as $it) {
                $det_id = (int)$it['id'];
                $k_dev = "dev_{$det_id}";
                $k_dan = "dan_{$det_id}";
                $k_per = "per_{$det_id}";
                $add_dev = (int)($input[$k_dev] ?? 0);
                $add_dan = (int)($input[$k_dan] ?? 0);
                $add_per = (int)($input[$k_per] ?? 0);
                if (
                    $add_dev < 0 ||
                    $add_dan < 0 ||
                    $add_per < 0
                ) {
                    throw new RuntimeException(
                        "No se permiten valores negativos."
                    );
                }
                $add_total =
                $add_dev +
                $add_dan +
                $add_per;
                if ($add_total === 0) {
                    continue;
                }
                $cerrado =
                (int)$it['devuelto'] +
                (int)$it['danado'] +
                (int)$it['perdido'];
                $pendiente =
                (int)$it['entregado'] -
                $cerrado;
                if ($add_total > $pendiente) {
                    throw new RuntimeException(
                        "Devolución/daño/pérdida excede pendiente en {$it['codigo']} ({$it['nombre']}). Pendiente: {$pendiente}."
                    );
                }
                $up = $this->repository->devolver_update_reserva_detalle([
                        $add_dev,
                        $add_dan,
                        $add_per,
                        $det_id,
                        $id
                ]);
                // Devuelto
                if ($add_dev > 0) {
                    $nota =
                    "Devolución parcial. Reserva {$res['codigo']}.";
                    if ($obs_bodega !== '') {
                        $nota .= " Obs bodega: {$obs_bodega}";
                    }
                    $this->repository->add_mov('DEVOLUCION',
                        (int)$it['articulo_id'],
                        $id,
                        $add_dev,
                        $nota,
                        (int)$actor['id']
                    );
                }
                // Dañado
                if ($add_dan > 0) {
                    $st = $this->repository->devolver_update_articulos([
                            $add_dan,
                            (int)$it['articulo_id']
                    ]);
                    $nota =
                    "Daño en devolución. Reserva {$res['codigo']}. Baja en stock ACTIVO.";
                    if ($obs_bodega !== '') {
                        $nota .= " Obs bodega: {$obs_bodega}";
                    }
                    $this->repository->add_mov('AJUSTE',
                        (int)$it['articulo_id'],
                        $id,
                        -$add_dan,
                        $nota,
                        (int)$actor['id']
                    );
                }
                // Perdido
                if ($add_per > 0) {
                    $st = $this->repository->devolver_update_articulos_2([
                            $add_per,
                            $add_per,
                            (int)$it['articulo_id']
                    ]);
                    $nota =
                    "Pérdida en devolución. Reserva {$res['codigo']}. Baja en stock TOTAL y ACTIVO.";
                    if ($obs_bodega !== '') {
                        $nota .= " Obs bodega: {$obs_bodega}";
                    }
                    $this->repository->add_mov('AJUSTE',
                        (int)$it['articulo_id'],
                        $id,
                        -$add_per,
                        $nota,
                        (int)$actor['id']
                    );
                }
            }
            // Extras
            foreach ($extras as $e) {
                $eid = (int)$e['id'];
                $k_dev = "dev_extra_{$eid}";
                $k_obs = "obs_extra_{$eid}";
                $add_dev = (int)($input[$k_dev] ?? 0);
                $obs = trim((string)($input[$k_obs] ?? ''));
                if ($add_dev < 0) {
                    throw new RuntimeException(
                        "No se permiten negativos en extras."
                    );
                }
                if ($add_dev === 0 && $obs === '') {
                    continue;
                }
                $pend =
                (int)$e['entregado'] -
                (int)$e['devuelto'];
                if ($add_dev > $pend) {
                    throw new RuntimeException(
                        "Devolución excede pendiente en EXTRA: {$e['descripcion']}. Pendiente: {$pend}."
                    );
                }
                if ($add_dev > 0) {
                    $up = $this->repository->devolver_update_reserva_extras([
                            $add_dev,
                            $eid,
                            $id
                    ]);
                }
                if ($obs !== '') {
                    $up2 = $this->repository->devolver_update_reserva_extras_2([
                            $obs,
                            $eid,
                            $id
                    ]);
                }
            }
            $items2  = $this->repository->get_detalle($id);
            $extras2 = $this->repository->get_extras($id);
            if (
                $this->all_closed_inv($items2) &&
                $this->all_closed_extras($extras2)
            ) {
                $this->repository->set_estado($id, 'DEVUELTA');
            }
            $this->repository->commit();
            $result->message('ok', "Devolución registrada.");
            return $result->redirect("reserva_operacion.php?id={$id}");
        } catch (Throwable $e) {
            if ($this->repository->inTransaction()) {
                $this->repository->rollBack();
            }
            $error = $e->getMessage();
        }
        return $result->withData(['error' => $error]);
    }
}
