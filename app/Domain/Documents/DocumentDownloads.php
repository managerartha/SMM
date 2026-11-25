<?php

namespace App\Domain\Documents;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Files\FileStore;
use App\Domain\Files\PdfTools;
use App\Models\DocumentRevision;
use App\Models\User;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduhan dokumen terkendali — selalu lewat sini (cek hak akses + tanda air + log).
 */
class DocumentDownloads
{
    public function __construct(private FileStore $files, private PdfTools $pdf, private ActivityLogger $log) {}

    public function revision(User $user, DocumentRevision $rev, string $kind = 'pdf'): BinaryFileResponse
    {
        $ability = $kind === 'source' ? 'downloadSource' : 'downloadRevision';
        Gate::forUser($user)->authorize($ability, $rev);

        $file = $kind === 'source' ? $rev->sourceFile : $rev->pdfFile;
        abort_if(! $file, 404, 'Berkas tidak tersedia.');

        $tmp = $this->files->toTemp($file);
        $stamp = Format::dateTime(now());
        $label = $rev->fullLabel();

        if ($file->isPdf()) {
            [$big, $line] = match ($rev->status) {
                DocumentRevision::EFFECTIVE => [null, "SALINAN TERKENDALI - {$label} - diunduh oleh {$user->name} - {$stamp}. Salinan cetak tidak terkendali; periksa versi berlaku di SMM."],
                DocumentRevision::EXPIRED => ['TIDAK TERKENDALI', "TIDAK TERKENDALI - {$label} telah kedaluwarsa - diunduh oleh {$user->name} - {$stamp}"],
                default => ['DRAF - BELUM BERLAKU', "DRAF {$rev->statusLabel()} - {$label} - diunduh oleh {$user->name} - {$stamp}"],
            };
            $skip = $rev->status === DocumentRevision::EFFECTIVE && ! Settings::get('watermark.controlled_copy', true);
            if (! $skip) {
                $out = $tmp.'.wm.pdf';
                $this->pdf->watermark($tmp, $out, $big, $line);
                @unlink($tmp);
                $tmp = $out;
            }
        }

        $this->log->log('unduh', "Mengunduh {$label} ({$rev->statusLabel()}, ".($kind === 'source' ? 'file sumber' : 'PDF').')', $rev->document, ['revision_id' => $rev->id, 'file' => $file->uuid], $user);

        $name = $this->files->cleanName($label.' - '.$rev->document->title).($kind === 'source' ? ' (sumber)' : '').'.'.$file->extension;

        return response()->download($tmp, $name, ['Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'])
            ->deleteFileAfterSend();
    }
}
