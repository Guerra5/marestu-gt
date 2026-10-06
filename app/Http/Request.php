<?php
declare(strict_types=1);
namespace Marestu\Http;

final class Request {
    public function __construct(public readonly string $method, public readonly array $query = [], public readonly array $input = []) {}

    public static function fromGlobals(): self {
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $_GET, $_POST);
    }

    public function isPost(): bool { return $this->method === 'POST'; }
}
