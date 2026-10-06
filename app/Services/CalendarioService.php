<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\CalendarioRepository;

final class CalendarioService {

    public function __construct(private readonly CalendarioRepository $repository) {}

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();

        return $result->withData([]);
    }
}
