<?php

namespace App\Http\Controllers;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Files\FileStore;
use App\Models\Complaint;
use App\Models\Ncr;
use App\Models\NcrAction;
use App\Models\NcrEffectivenessCheck;
use App\Models\Risk;
use App\Models\StoredFile;
use Illuminate\Http\Request;

/** Unduhan lampiran modul Tahap 2 (keluhan, NCR & bukti tindakan/efektivitas, risiko) dengan cek hak akses. */
class AttachmentController extends Controller
{
    public function __invoke(Request $request, string $uuid, FileStore $files, ActivityLogger $log)
    {
        $file = StoredFile::where('uuid', $uuid)->firstOrFail();
        $owner = $file->attachable;
        $root = match (true) {
            $owner instanceof Complaint, $owner instanceof Ncr, $owner instanceof Risk => $owner,
            $owner instanceof NcrAction, $owner instanceof NcrEffectivenessCheck => $owner->ncr,
            default => null,
        };
        $user = $request->user();
        if ($owner instanceof \App\Models\QualityRecord) {
            abort_unless(\App\Models\QualityRecord::whereKey($owner->id)->visibleTo($user)->exists(), 403);
            $root = $owner;
        } elseif ($owner instanceof \App\Models\Facility || $owner instanceof \App\Models\FacilityLog) {
            abort_unless($user->can('sarana.lihat'), 403);
            $root = $owner instanceof \App\Models\Facility ? $owner : $owner->facility;
        } elseif ($owner instanceof \App\Models\Supplier) {
            abort_unless($user->can('pemasok.lihat'), 403);
            $root = $owner;
        } else {
            abort_if(! $root, 404);
            $this->authorize('view', $root);
        }

        $tmp = $files->toTemp($file);
        $log->log('unduh', "Mengunduh lampiran {$file->original_name} ({$root->number})", $root, ['file' => $file->uuid]);

        return response()->download($tmp, $file->original_name, ['Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend();
    }
}
