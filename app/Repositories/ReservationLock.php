<?php
declare(strict_types=1);
namespace Marestu\Repositories;

trait ReservationLock {
    public function withReservationTransaction(int $id, int $additionalArticle, callable $work): mixed {
        $this->pdo->exec('SET innodb_lock_wait_timeout = 5');
        return $this->transaction(function () use ($id, $additionalArticle, $work) {
            // All reservation writers take the reservation lock before reading state.
            $this->statement('SELECT id FROM reservas WHERE id=? FOR UPDATE', [$id])->fetch();
            $ids = $this->statement('SELECT articulo_id FROM reserva_detalle WHERE reserva_id=?', [$id])->fetchAll(\PDO::FETCH_COLUMN);
            if ($additionalArticle > 0) $ids[] = $additionalArticle;
            $ids = array_unique(array_map('intval', $ids));
            sort($ids, SORT_NUMERIC);
            // Serialize availability checks across different reservations for an article.
            foreach ($ids as $articleId) {
                $this->statement('SELECT id FROM articulos WHERE id=? FOR UPDATE', [$articleId])->fetch();
            }
            return $work();
        });
    }

    public function availableForReservation(int $articleId, string $start, string $end, int $reservationId): int {
        $row = $this->statement("SELECT a.cantidad_activa - COALESCE((
            SELECT SUM(d.cantidad) FROM reserva_detalle d
            JOIN reservas r ON r.id=d.reserva_id
            WHERE d.articulo_id=a.id AND r.id<>?
              AND r.estado IN ('CONFIRMADA','ENTREGADA')
              AND r.fecha_salida<=? AND r.fecha_retorno>=?
        ),0) AS disponible FROM articulos a WHERE a.id=?", [$reservationId, $end, $start, $articleId])->fetch();
        return $row ? (int)$row['disponible'] : 0;
    }
}
