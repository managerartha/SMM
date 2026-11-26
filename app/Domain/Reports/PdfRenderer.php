<?php

namespace App\Domain\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** HTML (Blade) → PDF dengan dompdf. Sumber eksternal dimatikan; font DejaVu Sans (mendukung huruf Indonesia). */
class PdfRenderer
{
    public function render(string $view, array $data, string $orientation = 'portrait'): string
    {
        $html = view($view, $data)->render();
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot(base_path());
        $tmp = storage_path('app/private/tmp');
        File::ensureDirectoryExists($tmp, 0700);
        $options->setTempDir($tmp);
        $options->setFontCache($tmp);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();
        $path = $tmp.'/'.Str::random(24).'.pdf';
        file_put_contents($path, $dompdf->output());

        return $path;
    }
}
