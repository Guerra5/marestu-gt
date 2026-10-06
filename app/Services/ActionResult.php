<?php
declare(strict_types=1);
namespace Marestu\Services;

final class ActionResult {
    public array $data = [];
    public array $messages = [];
    public ?array $authenticatedUser = null;
    public ?string $destination = null;
    public ?string $errorBody = null;
    public int $status = 200;

    public function message(string $key, string $message): void { $this->messages[$key] = $message; }

    public function redirect(string $destination): self {
        $this->destination = $destination;
        return $this;
    }

    public function stop(string $body, int $status = 400): self {
        $this->errorBody = $body;
        $this->status = $status;
        return $this;
    }

    public function withData(array $data): self {
        $this->data = $data;
        return $this;
    }
}
