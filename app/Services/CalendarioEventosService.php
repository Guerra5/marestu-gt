<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\CalendarioEventosRepository;

final class CalendarioEventosService {
    public function __construct(private readonly CalendarioEventosRepository $repository) {}

    public function events(array $query): array {
        $start = substr((string)($query['start'] ?? ''), 0, 10);
        $end = substr((string)($query['end'] ?? ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) return [];
        $colors = ['CONFIRMADA' => '#0d6efd', 'ENTREGADA' => '#212529', 'DEVUELTA' => '#198754', 'BORRADOR' => '#6c757d', 'CANCELADA' => '#dc3545'];
        $events = [];
        foreach ($this->repository->between($start, $end, ($query['only'] ?? '') === 'open') as $row) {
            $color = $colors[$row['estado']] ?? '#0d6efd';
            $events[] = [
                'id' => (int)$row['id'],
                'title' => $row['codigo'] . ' · ' . $row['cliente'],
                'start' => $row['fecha_salida'],
                'end' => date('Y-m-d', strtotime($row['fecha_retorno'] . ' +1 day')),
                'allDay' => true,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'estado' => (string)$row['estado'], 'telefono' => (string)$row['telefono'],
                    'codigo' => (string)$row['codigo'], 'cliente' => (string)$row['cliente'],
                    'salida' => (string)$row['fecha_salida'], 'retorno' => (string)$row['fecha_retorno'],
                ],
            ];
        }
        return $events;
    }
}
