<?php
declare(strict_types=1);
namespace Marestu\Services\Articulos\Actions;

use Throwable;
use Marestu\Services\ActionResult;

trait StockAction {
    public function stock(array $input, array $context, array $actor): ActionResult {
        $result = new ActionResult();
        $error = null;
        $id = (int)($input['id'] ?? 0);
        $nuevo_total = (int)($input['cantidad_total'] ?? 0);
        $nuevo_activo = (int)($input['cantidad_activa'] ?? 0);
        $nota_user = trim((string)($input['nota'] ?? ''));
        if ($id <= 0) {
            $result->message('err', "ID inválido.");
            return $result->redirect('articulos.php');
        }
        if ($nuevo_total < 0 || $nuevo_activo < 0) {
            $error = "Las cantidades no pueden ser negativas.";
        } elseif ($nuevo_activo > $nuevo_total) {
            $error = "La cantidad activa no puede ser mayor que la total.";
        } else {
            $st = $this->repository->stock_select_articulos([$id]);
            $row = $st->fetch();
            if (!$row) {
                $result->message('err', "Artículo no encontrado.");
                return $result->redirect('articulos.php');
            }
            $old_total = (int)$row['cantidad_total'];
            $old_activo = (int)$row['cantidad_activa'];
            $diff_activo = $nuevo_activo - $old_activo;
            $this->repository->beginTransaction();
            try {
                $up = $this->repository->stock_update_articulos([$nuevo_total, $nuevo_activo, $id]);
                // Kardex: registramos solo si cambió el ACTIVO (lo alquilable)
                if ($diff_activo !== 0) {
                    $tipo = 'AJUSTE';
                    $nota = "Ajuste stock. Total: {$old_total}→{$nuevo_total}, Activo: {$old_activo}→{$nuevo_activo}.";
                    if ($nota_user !== '') $nota .= " Nota: {$nota_user}";
                    $mov = $this->repository->stock_insert_into_movimientos_inventario([$tipo, $id, $diff_activo, $nota, (int)$actor['id']]);
                }
                $this->repository->commit();
                $result->message('ok', "Stock actualizado.");
                return $result->redirect('articulos.php?edit=' . $id);
            } catch (Throwable $e) {
                $this->repository->rollBack();
                $error = $e->getMessage();
            }
        }
        return $result->withData(['error' => $error]);
    }
}
