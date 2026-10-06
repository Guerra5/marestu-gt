<?php
declare(strict_types=1);
namespace Marestu\Services;

use Marestu\Repositories\UsuarioEdicionRepository;

final class UsuarioEdicionService {
    use UsuarioEdicion\Actions\SaveAction;

    public function __construct(private readonly UsuarioEdicionRepository $repository) {}

    public function execute(array $query, array $input, array $actor, bool $submitted, array $feedback = []): ActionResult {
        $result = new ActionResult();
        $id = (int)($query['id'] ?? 0);
        if ($id <= 0) { return $result->stop("ID inválido."); }
        $st = $this->repository->load_select_usuarios([$id]);
        $row = $st->fetch();
        if (!$row) { return $result->stop("Usuario no existe."); }
        $error = null;
        $nombre = (string)$row['nombre'];
        $usuario = (string)$row['usuario'];
        $rol = (string)$row['rol'];
        $activo = (int)$row['activo'];
        if ($submitted) {
            $actionResult = $this->save($input, compact('id'), $actor);
            if ($actionResult->destination !== null || $actionResult->errorBody !== null) return $actionResult;
            extract($actionResult->data, EXTR_OVERWRITE);
        }
        return $result->withData(compact('id', 'error', 'nombre', 'usuario', 'rol', 'activo'));
    }
}
