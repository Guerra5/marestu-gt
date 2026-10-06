<?php
declare(strict_types=1);

return [
    'index' => \Marestu\Controllers\DashboardController::class,
    'clientes' => \Marestu\Controllers\ClientesController::class,
    'categorias' => \Marestu\Controllers\CategoriasController::class,
    'articulos' => \Marestu\Controllers\ArticulosController::class,
    'reservas' => \Marestu\Controllers\ReservasController::class,
    'reserva_editar' => \Marestu\Controllers\ReservaEdicionController::class,
    'reserva_operacion' => \Marestu\Controllers\ReservaOperacionController::class,
    'usuarios' => \Marestu\Controllers\UsuariosController::class,
    'usuario_nuevo' => \Marestu\Controllers\UsuarioNuevoController::class,
    'usuario_editar' => \Marestu\Controllers\UsuarioEdicionController::class,
    'kardex' => \Marestu\Controllers\KardexController::class,
    'reserva_cotizacion' => \Marestu\Controllers\CotizacionController::class,
    'reserva_nota_entrega' => \Marestu\Controllers\NotaEntregaController::class,
    'login' => \Marestu\Controllers\LoginController::class,
    'calendario' => \Marestu\Controllers\CalendarioController::class,
    'api_reservas' => \Marestu\Controllers\CalendarioEventosController::class,
    'logout' => \Marestu\Controllers\LogoutController::class,
    'usuario_toggle' => \Marestu\Controllers\UsuarioToggleController::class,
    'reserva_pdf' => \Marestu\Controllers\DocumentoController::class,
];
