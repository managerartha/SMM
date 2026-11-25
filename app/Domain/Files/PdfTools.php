<?php

namespace App\Domain\Files;

use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Tanda air PDF dengan FPDI.
 * FPDI gratis tidak bisa membaca PDF dengan "compressed xref". Saat unggah, PDF diuji;
 * bila gagal dan qpdf tersedia, PDF dinormalisasi. Bila tetap gagal, unggahan ditolak dengan petunjuk.
 * Dengan begitu tanda air saat unduh selalu bisa dipasang.
 */
class PdfTools
{
    public function canWatermark(string $path): bool
    {
        try {
            $pdf = new Fpdi;
            $count = $pdf->setSourceFile($path);

            return $count > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /** Pastikan PDF bisa diberi tanda air; kembalikan jalur PDF (bisa berkas baru hasil normalisasi). */
    public function ensureWatermarkable(string $path, string $field = 'pdf'): string
    {
        if ($this->canWatermark($path)) {
            return $path;
        }
        $normalized = $this->normalizeWithQpdf($path);
        if ($normalized && $this->canWatermark($normalized)) {
            return $normalized;
        }

        throw ValidationException::withMessages([$field => 'PDF ini tidak bisa diproses untuk tanda air. Simpan ulang dari Word: File → Save As → PDF → Options → centang "PDF/A compliant", lalu unggah lagi.']);
    }

    public function normalizeWithQpdf(string $path): ?string
    {
        if (! function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return null;
        }
        $bin = trim((string) @shell_exec('command -v qpdf 2>/dev/null'));
        if ($bin === '') {
            return null;
        }
        $out = $path.'.norm.pdf';
        exec(escapeshellarg($bin).' --object-streams=disable --decrypt '.escapeshellarg($path).' '.escapeshellarg($out).' 2>&1', $o, $code);

        return in_array($code, [0, 3], true) && is_file($out) ? $out : null;
    }

    /**
     * Pasang tanda air.
     * $big  : teks diagonal besar (mis. "TIDAK TERKENDALI"), null = tanpa.
     * $line : teks kecil di bawah halaman (mis. "SALINAN TERKENDALI – nama – tanggal").
     */
    public function watermark(string $src, string $dest, ?string $big, string $line): void
    {
        $pdf = new WatermarkPdf;
        $pdf->SetAutoPageBreak(false);
        $pages = $pdf->setSourceFile($src);
        for ($i = 1; $i <= $pages; $i++) {
            $tpl = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);

            if ($big) {
                $pdf->SetFont('Helvetica', 'B', min($size['width'], $size['height']) / 7);
                $pdf->SetTextColor(220, 38, 38);
                $pdf->setAlpha(0.25);
                $w = $pdf->GetStringWidth($this->enc($big));
                $cx = $size['width'] / 2;
                $cy = $size['height'] / 2;
                $pdf->rotatedText($cx - $w / 2 * cos(deg2rad(35)), $cy + $w / 2 * sin(deg2rad(35)), $this->enc($big), 35);
                $pdf->setAlpha(1);
            }

            $pdf->SetFont('Helvetica', 'B', 7);
            $pdf->SetTextColor($big ? 220 : 30, $big ? 38 : 64, $big ? 38 : 175);
            $pdf->SetXY(5, $size['height'] - 7);
            $pdf->Cell($size['width'] - 10, 4, $this->enc($line), 0, 0, 'C');
        }
        $pdf->Output('F', $dest);
    }

    private function enc(string $s): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $s) ?: $s;
    }
}
