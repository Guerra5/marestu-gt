<?php
declare(strict_types=1);
namespace Marestu\Services;

final class PdfRenderer {
    public function render(string $html): string {
        $document = new \Dompdf\Dompdf();
        $document->loadHtml($html);
        $document->setPaper('letter', 'portrait');
        $document->render();
        return $document->output();
    }
}
