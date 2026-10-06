<?php
declare(strict_types=1);

function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float $value): string {
    return 'Q ' . number_format($value, 2, '.', ',');
}
