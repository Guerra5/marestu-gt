<?php
declare(strict_types=1);
namespace Marestu\Controllers;

use Marestu\Http\Request;
use Marestu\Http\Response;
use Marestu\Http\View;
use Marestu\Repositories\DocumentoRepository;
use Marestu\Services\DocumentoService;
use Marestu\Services\PdfRenderer;

final class DocumentoController extends Controller {
    public function __invoke(Request $request): Response {
        require_login();
        if (!is_admin()) return new Response('No autorizado.', 403);
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (is_file($autoload)) require_once $autoload;
        if (!class_exists(\Dompdf\Dompdf::class)) {
            error_log('Dompdf no está instalado en src/vendor.');
            return new Response('La generación de PDF no está disponible. Podés utilizar la versión imprimible del documento.', 503);
        }
        $service = new DocumentoService(new DocumentoRepository(db()));
        $result = $service->data((int)($request->query['id'] ?? 0), (string)($request->query['type'] ?? 'cotizacion'));
        if ($result->errorBody !== null) return new Response($result->errorBody, $result->status);
        $data = $result->data;
        $data['pdfCss'] = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/pages/reserva_pdf.css');
        $pdf = (new PdfRenderer())->render(View::render('reserva_pdf/index', $data));
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['res']['codigo'] . '_' . $data['type']) . '.pdf';
        return new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $filename . '"']);
    }
}
