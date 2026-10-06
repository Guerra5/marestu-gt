<?php
declare(strict_types=1);
namespace Marestu\Http;

final class Response {
    public function __construct(public readonly string $body = '', public readonly int $status = 200, public readonly array $headers = []) {}

    public static function redirect(string $location): self {
        return new self('', 302, ['Location' => $location]);
    }

    public static function json(array $data): self {
        return new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public function send(): void {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) header($name . ': ' . $value);
        echo $this->body;
    }
}
