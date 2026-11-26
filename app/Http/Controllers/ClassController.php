<?php

namespace App\Http\Controllers;

use App\Domain\Lms\ApiLmsSource;
use App\Domain\Lms\CsvLmsSource;
use App\Domain\Lms\LmsImporter;
use App\Domain\Surveys\SurveyService;
use App\Models\LmsImport;
use App\Models\TrainingClass;
use App\Models\TrainingProgram;
use App\Support\ExcelExport;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Kelas dari LMS (baca-saja) + impor CSV/Excel + sinkron API. */
class ClassController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('kelas.lihat'), 403);

        return view('classes.index', [
            'items' => $this->query($request)->paginate(30)->withQueryString(),
            'programs' => TrainingProgram::orderBy('name')->get(),
            'lastImport' => LmsImport::where('kind', 'kelas')->latest()->first(),
            'apiConfigured' => ApiLmsSource::configured(),
        ]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('kelas.lihat'), 403);
        $rows = $this->query($request)->get()->map(fn (TrainingClass $c) => [
            $c->class_code, $c->program_name, $c->scheme, $c->client, Format::date($c->start_date), Format::date($c->end_date), $c->trainers,
            $c->participants, $c->attended, $c->passed, $c->average_score, $c->attendanceRate(), $c->passRate(), $c->records()->count(),
        ]);

        return ExcelExport::download('Daftar Kelas', ['Kode kelas', 'Program', 'Skema', 'Klien', 'Mulai', 'Selesai', 'Trainer', 'Peserta', 'Hadir penuh', 'Lulus', 'Rata-rata nilai', '% hadir', '% lulus', 'Catatan mutu'], $rows);
    }

    public function show(Request $request, TrainingClass $class, SurveyService $surveys)
    {
        abort_unless($request->user()->can('kelas.lihat'), 403);
        $class->load(['program']);
        $records = $class->records()->with(['form', 'creator', 'attachments'])->visibleTo($request->user())->get();

        return view('classes.show', [
            'class' => $class,
            'records' => $records,
            'surveySummary' => $request->user()->can('survei.lihat') ? $surveys->summary(classId: $class->id) : null,
            'responses' => $request->user()->can('survei.lihat') ? $class->surveys()->orderByDesc('responded_at')->limit(100)->get() : collect(),
        ]);
    }

    public function importForm(Request $request)
    {
        abort_unless($request->user()->can('lms.impor'), 403);

        return view('classes.import', [
            'imports' => LmsImport::with('importer')->latest()->limit(20)->get(),
            'apiConfigured' => ApiLmsSource::configured(),
        ]);
    }

    public function import(Request $request, LmsImporter $importer)
    {
        abort_unless($request->user()->can('lms.impor'), 403);
        $data = $request->validate(['kind' => ['required', 'in:kelas,survei'], 'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx']], [], ['file' => 'berkas']);
        $file = $request->file('file');
        $source = new CsvLmsSource($file->getRealPath(), $file->getClientOriginalName());
        $result = $data['kind'] === 'kelas' ? $importer->importClasses($source, $request->user()) : $importer->importSurveys($source, $request->user());

        return redirect()->route('classes.import')->with('status', "Impor {$data['kind']} selesai: {$result->created_count} baru, {$result->updated_count} diperbarui, {$result->skipped_count} dilewati.")
            ->with('import_errors', $result->errors ?? []);
    }

    public function sync(Request $request, LmsImporter $importer)
    {
        abort_unless($request->user()->can('lms.impor'), 403);
        abort_unless(ApiLmsSource::configured(), 422, 'API LMS belum dikonfigurasi.');
        try {
            $since = now()->subDays(90)->toDateString();
            $a = $importer->importClasses(ApiLmsSource::fromConfig($since), $request->user(), 'api');
            $b = $importer->importSurveys(ApiLmsSource::fromConfig($since), $request->user(), 'api');
            $c = $importer->importComplaints(ApiLmsSource::fromConfig($since), $request->user(), 'api');
        } catch (\Throwable $e) {
            return back()->with('error', 'Sinkron API gagal: '.$e->getMessage());
        }

        return back()->with('status', "Sinkron API: kelas {$a->created_count} baru/{$a->updated_count} diperbarui; survei {$b->created_count} baru; keluhan {$c->created_count} baru.");
    }

    /** Template impor (.xlsx) dengan judul kolom yang dikenali. */
    public function template(Request $request, string $kind)
    {
        abort_unless($request->user()->can('lms.impor'), 403);
        [$headers, $example] = $kind === 'survei'
            ? [['id_respons', 'kode_kelas', 'jenis_survei', 'tanggal', 'skor', 'aspek_materi', 'aspek_trainer', 'aspek_fasilitas', 'aspek_pelayanan', 'trainer', 'komentar'],
                ['R-0001', 'AK3U-2027-01', 'kepuasan_peserta', '2027-01-20', '', 4, 5, 3, 4, '', 'Ruang kelas agak panas']]
            : [['kode_kelas', 'program', 'kode_program', 'skema', 'klien', 'lokasi', 'tgl_mulai', 'tgl_selesai', 'trainer', 'jumlah_peserta', 'hadir', 'lulus', 'rata_nilai', 'status'],
                ['AK3U-2027-01', 'Ahli K3 Umum', 'AK3U', 'Kemnaker RI', '', 'Jakarta', '2027-01-06', '2027-01-17', 'Hendra Gunawan', 25, 24, 23, 81.5, 'selesai']];
        $dir = storage_path('app/private/tmp');
        @mkdir($dir, 0700, true);
        $path = $dir.'/'.Str::random(20).'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues($headers));
        $w->addRow(Row::fromValues($example));
        $w->close();

        return response()->download($path, "template-impor-{$kind}.xlsx")->deleteFileAfterSend();
    }

    private function query(Request $request)
    {
        return TrainingClass::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('class_code', 'like', $like)->orWhere('program_name', 'like', $like)->orWhere('client', 'like', $like)->orWhere('trainers', 'like', $like));
            })
            ->when($request->filled('program'), fn ($q) => $q->where('training_program_id', $request->integer('program')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('start_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('start_date', '<=', $request->date('to')))
            ->orderByDesc('start_date')->orderByDesc('id');
    }
}
