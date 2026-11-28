<?php

namespace App\Domain\Evidence;

use App\Domain\Activity\ActivityLogger;
use App\Domain\Files\FileStore;
use App\Domain\Files\PdfTools;
use App\Domain\Reports\PdfRenderer;
use App\Domain\Surveys\SurveyService;
use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\AuditProgram;
use App\Models\BackupRun;
use App\Models\Complaint;
use App\Models\Document;
use App\Models\EvidenceCategory;
use App\Models\EvidencePackage;
use App\Models\ExternalDocument;
use App\Models\Facility;
use App\Models\Innovation;
use App\Models\Kpi;
use App\Models\ManagementReview;
use App\Models\Ncr;
use App\Models\NcrEffectivenessCheck;
use App\Models\PerformanceReport;
use App\Models\Personnel;
use App\Models\QualityRecord;
use App\Models\StoredFile;
use App\Models\SurveyResponse;
use App\Support\ExcelExport;
use App\Support\Format;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Paket Bukti: ZIP dengan folder 01_SKP_PJK3 … 16_Sistem_Digital + 00_Daftar_Isi.pdf.
 * Isi: dokumen BERLAKU (terkendali & eksternal) + catatan/rekaman dalam rentang tanggal.
 * Berkas diambil dari Google Drive ke folder sementara, di-ZIP, lalu ZIP disimpan kembali ke Drive (SMM/Paket Bukti).
 */
class EvidencePackageBuilder
{
    private string $root;
    /** @var array<int, array<int, array{path:string, source:string}>> */
    private array $index = [];
    private array $warnings = [];
    private array $folders = [];

    public function __construct(private FileStore $files, private PdfTools $pdfTools, private PdfRenderer $pdf, private SurveyService $surveys, private ActivityLogger $log) {}

    public function build(EvidencePackage $pkg): EvidencePackage
    {
        $pkg->update(['status' => 'proses', 'error' => null]);
        $this->root = storage_path('app/private/tmp/paket-'.$pkg->id.'-'.Str::random(6));
        File::ensureDirectoryExists($this->root, 0700);
        $this->index = $this->warnings = [];
        $from = $pkg->date_from->toDateString();
        $to = $pkg->date_to->toDateString();
        $this->folders = EvidenceCategory::orderBy('id')->pluck('folder_name', 'id')->all();

        try {
            for ($n = 1; $n <= 16; $n++) {
                File::ensureDirectoryExists($this->dir($n));
                $this->generic($n, $from, $to);
            }
            $this->personnel(3, 'tenaga_ahli');
            $this->personnel(4, 'tenaga_teknis');
            $this->facilities($from, $to);
            $this->reports($from, $to);
            $this->surveyRecap($from, $to);
            $this->complaints($from, $to);
            $this->innovations($from, $to);
            $this->audits($from, $to);
            $this->ncrs($from, $to);
            $this->effectiveness($from, $to);
            $this->digitalSystem($pkg, $from, $to);

            $tocPath = $this->root.'/00_Daftar_Isi.pdf';
            File::copy($this->pdf->render('pdf.evidence-toc', ['pkg' => $pkg, 'folders' => $this->folders, 'index' => $this->index, 'warnings' => $this->warnings, 'categories' => EvidenceCategory::orderBy('id')->get()]), $tocPath);

            $zipPath = $this->root.'.zip';
            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile($tocPath, '00_Daftar_Isi.pdf');
            foreach ($this->folders as $n => $folder) {
                $zip->addEmptyDir($folder);
            }
            foreach (File::allFiles($this->root) as $f) {
                $rel = ltrim(str_replace($this->root, '', $f->getPathname()), '/');
                if ($rel !== '00_Daftar_Isi.pdf') {
                    $zip->addFile($f->getPathname(), $rel);
                }
            }
            $zip->close();

            $name = 'Paket Bukti '.$from.' sd '.$to.' ('.now()->format('Ymd-His').')';
            $stored = $this->files->storeGenerated($zipPath, 'zip', 'application/zip', 'Paket Bukti', $name, 'evidence_package', $pkg, $pkg->creator, $name.'.zip');
            $summary = [];
            foreach ($this->folders as $n => $folder) {
                $summary[$folder] = count($this->index[$n] ?? []);
            }
            $pkg->update(['status' => 'selesai', 'file_id' => $stored->id, 'summary' => ['folders' => $summary, 'warnings' => $this->warnings], 'finished_at' => now()]);
            $this->log->log('ekspor', "Paket Bukti {$from} s.d. {$to} dibuat (".array_sum($summary).' berkas)', $pkg, [], $pkg->creator);
            @unlink($zipPath);
        } catch (Throwable $e) {
            $pkg->update(['status' => 'gagal', 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
            report($e);
        } finally {
            File::deleteDirectory($this->root);
        }

        return $pkg->fresh();
    }

    // ---------------------------------------------------------------- pengumpul per kategori

    /** Dokumen terkendali & eksternal berlaku + catatan mutu (dalam rentang) sesuai kategori bukti. */
    private function generic(int $n, string $from, string $to): void
    {
        Document::with(['currentRevision.pdfFile'])->whereNull('archived_at')->whereNotNull('current_revision_id')->where('evidence_category_id', $n)->get()
            ->each(function (Document $d) use ($n) {
                $rev = $d->currentRevision;
                $this->copyFile($n, $rev->pdfFile, 'Dokumen Terkendali/'.$this->seg($d->number.' '.$rev->label().' - '.$d->title).'.pdf', "Salinan berlaku {$d->number} {$rev->label()}", 'SALINAN TERKENDALI - Paket Bukti - '.$d->number.' '.$rev->label().' - berlaku sejak '.Format::date($rev->effective_date));
            });
        ExternalDocument::with(['currentVersion.file'])->whereNull('archived_at')->whereNotNull('current_version_id')->where('evidence_category_id', $n)->get()
            ->each(function (ExternalDocument $d) use ($n) {
                $v = $d->currentVersion;
                $this->copyFile($n, $v->file, 'Dokumen Eksternal/'.$this->seg($d->number.' - '.$d->title.' (no. '.$v->original_number.')').'.'.$v->file->extension, "{$d->number} versi {$v->version_no}, berlaku s.d. ".($v->expires_at ? Format::date($v->expires_at) : 'tanpa batas'));
            });
        QualityRecord::with('attachments')->whereNull('archived_at')->where('evidence_category_id', $n)->whereBetween('activity_date', [$from, $to])->orderBy('activity_date')->get()
            ->each(function (QualityRecord $r) use ($n) {
                foreach ($r->attachments as $i => $f) {
                    $this->copyFile($n, $f, 'Catatan Mutu/'.$this->seg($r->number.' - '.$r->title).'/'.($i + 1).'_'.$this->seg($f->original_name), "Catatan {$r->number} ".Format::date($r->activity_date));
                }
            });
    }

    private function personnel(int $n, string $type): void
    {
        Personnel::with(['activeDocuments.file'])->where('type', $type)->where('status', 'aktif')->orderBy('number')->get()->each(function (Personnel $p) use ($n) {
            foreach ($p->activeDocuments as $d) {
                $this->copyFile($n, $d->file, $this->seg("{$p->number} - {$p->name}").'/'.$this->seg("{$d->category} - {$d->title}").'.'.$d->file->extension, "{$p->number} {$p->name}: {$d->title}".($d->expires_at ? ' (s.d. '.Format::date($d->expires_at).')' : ''));
            }
        });
    }

    private function facilities(string $from, string $to): void
    {
        $items = Facility::where('status', 'aktif')->orderBy('number')->get();
        $this->writeXlsx(6, 'Daftar_Sarana_Prasarana.xlsx', ['Nomor', 'Nama', 'Kategori', 'Lokasi', 'Jumlah', 'Kondisi', 'Pemeriksaan berikutnya', 'Kalibrasi berikutnya'],
            $items->map(fn ($f) => [$f->number, $f->name, $f->category, $f->location, $f->quantity, $f->condition, Format::date($f->next_inspection_at), $f->needs_calibration ? Format::date($f->next_calibration_at) : '–']), 'Daftar inventaris sarana & prasarana');
        foreach (Facility::with(['attachments', 'logs' => fn ($q) => $q->whereBetween('performed_at', [$from, $to])->with('attachments')])->where('status', 'aktif')->get() as $f) {
            foreach ($f->attachments as $i => $a) {
                $this->copyFile(6, $a, $this->seg("{$f->number} - {$f->name}").'/'.$this->seg("Foto {$i}_{$a->original_name}"), "Foto {$f->number}");
            }
            foreach ($f->logs as $l) {
                foreach ($l->attachments as $i => $a) {
                    $this->copyFile(6, $a, $this->seg("{$f->number} - {$f->name}").'/'.$this->seg("{$l->performed_at->format('Y-m-d')} {$l->type} {$i}_{$a->original_name}"), "{$l->type} {$f->number} ".Format::date($l->performed_at));
                }
            }
        }
    }

    private function reports(string $from, string $to): void
    {
        PerformanceReport::with('file')->where('status', 'disahkan')->get()
            ->filter(fn ($r) => $this->periodInRange($r->period, $from, $to))
            ->each(fn ($r) => $this->copyFile(9, $r->file, 'Laporan Kinerja/'.str_replace('/', '-', $r->number).'.pdf', "{$r->number} {$r->periodLabel()} – disahkan {$r->approved_by_name}"));
        $years = range((int) substr($from, 0, 4), (int) substr($to, 0, 4));
        $rows = [];
        foreach (Kpi::with('values')->whereIn('year', $years)->orderBy('year')->orderBy('code')->get() as $k) {
            foreach ($k->values as $v) {
                if ($v->period >= substr($from, 0, 7) && $v->period <= substr($to, 0, 7)) {
                    $rows[] = [$k->code, $k->name, $k->targetLabel(), $v->period, $v->value, $k->achieved($v->value) ? 'Tercapai' : 'Tidak tercapai', $v->is_auto ? 'Otomatis' : 'Manual'];
                }
            }
        }
        $this->writeXlsx(9, 'Realisasi_KPI.xlsx', ['Kode', 'KPI', 'Target', 'Periode', 'Realisasi', 'Status', 'Sumber'], $rows, 'Target vs realisasi sasaran mutu');
    }

    private function surveyRecap(string $from, string $to): void
    {
        $summary = $this->surveys->summary($from, $to);
        $this->writePdf(10, 'Rekap_Survei.pdf', 'pdf.survey-recap', ['summary' => $summary, 'from' => $from, 'to' => $to], 'Ringkasan hasil survei');
        $this->writeXlsx(10, 'Respons_Survei.xlsx', ['Tanggal', 'Jenis', 'Kode kelas', 'Skor', 'Komentar'],
            SurveyResponse::whereBetween('responded_at', [$from, $to])->orderBy('responded_at')->cursor()->map(fn ($r) => [$r->responded_at->format('Y-m-d'), $r->survey_type, $r->class_code, $r->score, $r->comment]), 'Seluruh respons survei (tanpa data pribadi)');
    }

    private function complaints(string $from, string $to): void
    {
        $this->writeXlsx(11, 'Register_Keluhan_Feedback.xlsx', ['Nomor', 'Jenis', 'Diterima', 'Sumber', 'Perihal', 'Status', 'Ditanggapi', 'Tanggapan', 'NCR'],
            Complaint::with('ncr')->whereBetween('received_at', [$from, $to])->orderBy('received_at')->get()->map(fn ($c) => [$c->number, $c->type, $c->received_at->format('Y-m-d'), $c->source, $c->subject, $c->status, $c->responded_at?->format('Y-m-d'), $c->response, $c->ncr?->number]),
            'Bukti penerapan SOP penanganan keluhan & feedback');
    }

    private function innovations(string $from, string $to): void
    {
        $items = Innovation::with(['attachments', 'proposer', 'pic'])->whereIn('status', ['disetujui', 'pelaksanaan', 'selesai'])
            ->where(fn ($q) => $q->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->orWhereBetween('completed_at', [$from.' 00:00:00', $to.' 23:59:59']))->get();
        foreach ($items as $i) {
            $base = $this->seg($i->number.' - '.$i->title);
            $this->writePdf(12, $base.'/Ringkasan.pdf', 'pdf.innovation', ['i' => $i], "{$i->number} {$i->title} ({$i->status})");
            foreach ($i->attachments as $n => $f) {
                $this->copyFile(12, $f, $base.'/Bukti '.($n + 1).'_'.$this->seg($f->original_name), "Bukti {$i->number}");
            }
        }
    }

    private function audits(string $from, string $to): void
    {
        AuditProgram::with('file')->where('status', 'disahkan')->whereBetween('year', [(int) substr($from, 0, 4), (int) substr($to, 0, 4)])->get()
            ->each(fn ($p) => $this->copyFile(13, $p->file, "Program Audit {$p->year}.pdf", "Program audit {$p->year} – disahkan {$p->approved_by_name}"));
        Audit::with(['file', 'attachments'])->where('status', 'selesai')->whereBetween('executed_at', [$from, $to])->get()->each(function (Audit $a) {
            $base = 'Laporan Audit/'.$this->seg($a->number.' - '.$a->process);
            $this->copyFile(13, $a->file, $base.'/Laporan.pdf', "Laporan {$a->number} – disahkan {$a->approved_by_name}");
            foreach ($a->attachments as $n => $f) {
                $this->copyFile(13, $f, $base.'/Bukti '.($n + 1).'_'.$this->seg($f->original_name), "Bukti audit {$a->number}");
            }
        });
    }

    private function ncrs(string $from, string $to): void
    {
        $items = Ncr::with(['division', 'pic', 'creator', 'verifier', 'complaint', 'actions.pic', 'actions.attachments', 'checks.checker', 'events', 'attachments'])
            ->where('status', '!=', Ncr::CANCELLED)->whereBetween('found_at', [$from, $to])->orderBy('id')->get();
        foreach ($items as $ncr) {
            $base = $this->seg($ncr->number.' - '.Str::limit($ncr->title, 60, ''));
            $this->writePdf(14, $base.'/Formulir NCR-CAPA.pdf', 'ncrs.print', ['ncr' => $ncr, 'pdf' => true], "{$ncr->number} ({$ncr->statusLabel()})");
            foreach ($ncr->attachments as $n => $f) {
                $this->copyFile(14, $f, $base.'/Temuan '.($n + 1).'_'.$this->seg($f->original_name), "Bukti temuan {$ncr->number}");
            }
            foreach ($ncr->actions as $a) {
                foreach ($a->attachments as $n => $f) {
                    $this->copyFile(14, $f, $base.'/Tindakan '.$a->id.'-'.($n + 1).'_'.$this->seg($f->original_name), "Bukti tindakan {$ncr->number}");
                }
            }
        }
        $this->writeXlsx(14, 'Register_NCR_CAPA.xlsx', ['Nomor', 'Ditemukan', 'Sumber', 'Judul', 'Tingkat', 'Status', 'Akar masalah', 'Ditutup'],
            $items->map(fn ($n) => [$n->number, $n->found_at->format('Y-m-d'), $n->source, $n->title, $n->severity, $n->statusLabel(), $n->root_cause, $n->closed_at?->format('Y-m-d')]), 'Register NCR & CAPA');
    }

    private function effectiveness(string $from, string $to): void
    {
        $checks = NcrEffectivenessCheck::with(['ncr', 'checker', 'attachments'])->whereNotNull('result')->whereBetween('checked_at', [$from.' 00:00:00', $to.' 23:59:59'])->orderBy('checked_at')->get();
        $this->writeXlsx(15, 'Pemantauan_Efektivitas.xlsx', ['NCR', 'Siklus', 'Hari ke-', 'Jadwal', 'Diperiksa', 'Hasil', 'Catatan', 'Oleh'],
            $checks->map(fn ($c) => [$c->ncr->number, $c->cycle, $c->day, $c->due_date->format('Y-m-d'), $c->checked_at->format('Y-m-d'), $c->result, $c->note, $c->checker?->name]), 'Laporan pemantauan efektivitas 30/60/90 hari');
        foreach ($checks as $c) {
            foreach ($c->attachments as $n => $f) {
                $this->copyFile(15, $f, str_replace('/', '-', $c->ncr->number).'/Hari '.$c->day.' - '.($n + 1).'_'.$this->seg($f->original_name), "Bukti efektivitas {$c->ncr->number} hari ke-{$c->day}");
            }
        }
    }

    private function digitalSystem(EvidencePackage $pkg, string $from, string $to): void
    {
        $stats = [
            'documents' => Document::whereNotNull('current_revision_id')->whereNull('archived_at')->count(),
            'records' => QualityRecord::whereBetween('activity_date', [$from, $to])->count(),
            'files' => StoredFile::where('state', StoredFile::STORED)->count(),
            'log_entries' => ActivityLog::whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])->count(),
            'log_intact' => app(\App\Domain\Activity\ActivityLogger::class)->verify() === [],
            'backups' => BackupRun::whereBetween('started_at', [$from.' 00:00:00', $to.' 23:59:59'])->get()->groupBy('status')->map->count()->all(),
            'last_backup' => BackupRun::where('status', 'berhasil')->latest('started_at')->first(),
        ];
        $this->writePdf(16, 'Sistem_Pengendalian_Mutu_Digital.pdf', 'pdf.digital-system', ['stats' => $stats, 'from' => $from, 'to' => $to], 'Uraian sistem & bukti pengendalian mutu digital (SMM)');
        ManagementReview::with('file')->where('status', 'disahkan')->whereBetween('meeting_date', [$from, $to])->get()
            ->each(fn ($m) => $this->copyFile(16, $m->file, 'Tinjauan Manajemen/'.str_replace('/', '-', $m->number).'.pdf', "Notulen {$m->number} – disahkan {$m->approved_by_name}"));
        $this->writeXlsx(16, 'Log_Aktivitas.xlsx', ['Waktu', 'Pengguna', 'Aksi', 'Keterangan', 'Hash'],
            ActivityLog::whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])->orderBy('id')->limit(20000)->cursor()->map(fn ($l) => [Format::dateTime($l->occurred_at), $l->user_name, $l->action, $l->description, $l->hash]), 'Jejak audit (log aktivitas berantai hash)');
    }

    // ---------------------------------------------------------------- utilitas

    /** Satu segmen nama berkas/folder: garis miring di nomor/judul (mis. no. SK 5/000076/…) tidak boleh memecah folder. */
    private function seg(string $name): string
    {
        return str_replace(['/', '\\'], '-', $name);
    }

    private function dir(int $n): string
    {
        return $this->root.'/'.($this->folders[$n] ?? sprintf('%02d', $n));
    }

    private function target(int $n, string $relative): string
    {
        $parts = array_map(fn ($p) => $this->files->cleanName($p) ?: 'berkas', explode('/', $relative));
        $path = $this->dir($n).'/'.implode('/', $parts);
        File::ensureDirectoryExists(dirname($path));
        $i = 2;
        $base = $path;
        while (file_exists($path)) {
            $path = preg_replace('/(\.[a-z0-9]+)?$/i', " ({$i})$1", $base, 1);
            $i++;
        }

        return $path;
    }

    private function copyFile(int $n, ?StoredFile $file, string $relative, string $source, ?string $stamp = null): void
    {
        if (! $file) {
            return;
        }
        try {
            $tmp = $this->files->toTemp($file);
            $dest = $this->target($n, $relative);
            if ($stamp && $file->isPdf()) {
                $this->pdfTools->watermark($tmp, $dest, null, $stamp);
                @unlink($tmp);
            } else {
                rename($tmp, $dest);
            }
            $this->index[$n][] = ['path' => $this->rel($dest), 'source' => $source];
        } catch (Throwable $e) {
            $this->warnings[] = "Tidak bisa mengambil {$file->original_name}: ".mb_substr($e->getMessage(), 0, 150);
        }
    }

    private function writeXlsx(int $n, string $relative, array $headers, iterable $rows, string $source): void
    {
        $dest = $this->target($n, $relative);
        $count = ExcelExport::toFile($dest, $headers, $rows);
        $this->index[$n][] = ['path' => $this->rel($dest), 'source' => $source." ({$count} baris)"];
    }

    private function writePdf(int $n, string $relative, string $view, array $data, string $source): void
    {
        $dest = $this->target($n, $relative);
        rename($this->pdf->render($view, $data), $dest);
        $this->index[$n][] = ['path' => $this->rel($dest), 'source' => $source];
    }

    private function rel(string $path): string
    {
        return ltrim(str_replace($this->root, '', $path), '/');
    }

    private function periodInRange(string $period, string $from, string $to): bool
    {
        if (strlen($period) === 4) {
            return $period >= substr($from, 0, 4) && $period <= substr($to, 0, 4);
        }

        return $period >= substr($from, 0, 7) && $period <= substr($to, 0, 7);
    }
}
