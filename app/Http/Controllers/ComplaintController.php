<?php

namespace App\Http\Controllers;

use App\Domain\Complaints\ComplaintService;
use App\Models\Complaint;
use App\Models\Division;
use App\Models\Ncr;
use App\Models\User;
use App\Support\ExcelExport;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    public function __construct(private ComplaintService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Complaint::class);

        return view('complaints.index', [
            'items' => $this->query($request)->paginate(25)->withQueryString(),
            'divisions' => Division::active()->orderBy('code')->get(),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Complaint::class);
        $rows = $this->query($request)->get()->map(fn (Complaint $c) => [
            $c->number, Complaint::TYPES[$c->type] ?? $c->type, Format::date($c->received_at), Complaint::SOURCES[$c->source] ?? $c->source,
            $c->reporter_name, $c->reporter_org, $c->class_code, Complaint::CATEGORIES[$c->category] ?? $c->category, $c->division?->code,
            $c->subject, $c->description, $c->severity, $c->pic?->name, Format::date($c->response_due_date), $c->statusLabel(),
            Format::date($c->responded_at), $c->response, $c->ncr?->number,
        ]);

        return ExcelExport::download('Register Keluhan dan Feedback', ['Nomor', 'Jenis', 'Diterima', 'Sumber', 'Pelapor', 'Perusahaan', 'Kode kelas', 'Kategori', 'Divisi', 'Perihal', 'Uraian', 'Tingkat', 'PIC', 'Batas tanggapan', 'Status', 'Ditanggapi', 'Tanggapan', 'NCR'], $rows);
    }

    public function create()
    {
        $this->authorize('create', Complaint::class);

        return view('complaints.create', ['divisions' => Division::active()->orderBy('code')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Complaint::class);
        $data = $request->validate($this->rules(true), [], $this->attributes());
        $c = $this->service->create($request->user(), $data, $request->file('attachments', []));

        return redirect()->route('complaints.show', $c)->with('status', "{$c->number} dicatat.");
    }

    public function show(Request $request, Complaint $complaint)
    {
        $this->authorize('view', $complaint);
        $complaint->load(['division', 'pic', 'creator', 'closer', 'ncr', 'attachments']);

        return view('complaints.show', [
            'c' => $complaint,
            'users' => User::active()->orderBy('name')->get(),
            'divisions' => Division::active()->orderBy('code')->get(),
        ]);
    }

    public function edit(Complaint $complaint)
    {
        $this->authorize('update', $complaint);

        return view('complaints.edit', ['c' => $complaint, 'divisions' => Division::active()->orderBy('code')->get()]);
    }

    public function update(Request $request, Complaint $complaint)
    {
        $data = $request->validate($this->rules(false), [], $this->attributes());
        $this->service->update($request->user(), $complaint, $data);

        return redirect()->route('complaints.show', $complaint)->with('status', 'Perubahan disimpan.');
    }

    public function assign(Request $request, Complaint $complaint)
    {
        $data = $request->validate(['pic_id' => ['required', 'integer'], 'response_due_date' => ['nullable', 'date']]);
        $this->service->assign($request->user(), $complaint, (int) $data['pic_id'], $data['response_due_date'] ?? null);

        return back()->with('status', 'Penanggung jawab ditetapkan.');
    }

    public function respond(Request $request, Complaint $complaint)
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:5000'], 'resolution' => ['nullable', 'string', 'max:5000'], 'attachments' => ['array'], 'attachments.*' => ['file']], [], ['response' => 'tanggapan']);
        $this->service->respond($request->user(), $complaint, $data['response'], $data['resolution'] ?? null, $request->file('attachments', []));

        return back()->with('status', "{$complaint->number} selesai ditangani.");
    }

    public function cancel(Request $request, Complaint $complaint)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->service->cancel($request->user(), $complaint, $data['note']);

        return back()->with('status', 'Dibatalkan.');
    }

    public function escalate(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'pic_id' => ['required', 'integer'],
            'severity' => ['required', Rule::in(['minor', 'mayor'])],
            'target_date' => ['required', 'date'],
        ], [], ['pic_id' => 'penanggung jawab', 'division_id' => 'divisi', 'target_date' => 'target selesai']);
        $ncr = $this->service->escalate($request->user(), $complaint, $data);

        return redirect()->route('ncrs.show', $ncr)->with('status', "{$ncr->number} dibuat dari {$complaint->number}.");
    }

    private function query(Request $request)
    {
        $user = $request->user();

        return Complaint::with(['division', 'pic', 'ncr'])->visibleTo($user)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), function ($q) use ($request) {
                match ($request->query('status')) {
                    'terbuka' => $q->whereIn('status', ['baru', 'diproses']),
                    'lewat' => $q->whereIn('status', ['baru', 'diproses'])->whereDate('response_due_date', '<', now()->toDateString()),
                    default => $q->where('status', $request->string('status')),
                };
            })
            ->when($request->filled('division'), fn ($q) => $q->where('division_id', $request->integer('division')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('subject', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhere('reporter_name', 'like', $like)->orWhere('reporter_org', 'like', $like)->orWhere('class_code', 'like', $like));
            })
            ->orderByDesc('received_at')->orderByDesc('id');
    }

    private function rules(bool $creating): array
    {
        $max = (int) \App\Support\Settings::get('upload.max_mb', 20) * 1024;

        return ($creating ? [
            'type' => ['required', Rule::in(array_keys(Complaint::TYPES))],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:'.$max],
        ] : []) + [
            'source' => ['required', Rule::in(array_keys(Complaint::SOURCES))],
            'channel' => ['nullable', Rule::in(array_keys(Complaint::CHANNELS))],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_contact' => ['nullable', 'string', 'max:255'],
            'reporter_org' => ['nullable', 'string', 'max:255'],
            'class_code' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(array_keys(Complaint::CATEGORIES))],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'severity' => ['nullable', Rule::in(array_keys(Complaint::SEVERITIES))],
            'response_due_date' => [$creating ? 'prohibited' : 'nullable', 'date'],
        ];
    }

    private function attributes(): array
    {
        return ['type' => 'jenis', 'received_at' => 'tanggal diterima', 'subject' => 'perihal', 'description' => 'uraian', 'source' => 'sumber'];
    }
}
