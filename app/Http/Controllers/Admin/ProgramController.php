<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    public function index()
    {
        return view('admin.programs', ['programs' => TrainingProgram::orderBy('code')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $p = TrainingProgram::create($data);
        $this->log->log('buat', "Menambah program pelatihan {$p->code} – {$p->name}", $p);

        return back()->with('status', 'Program ditambahkan.');
    }

    public function update(Request $request, TrainingProgram $program)
    {
        $program->update($this->validated($request, $program) + ['is_active' => $request->boolean('is_active')]);
        $this->log->log('ubah', "Mengubah program pelatihan {$program->code}", $program);

        return back()->with('status', 'Program diperbarui.');
    }

    private function validated(Request $request, ?TrainingProgram $p): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('training_programs', 'code')->ignore($p?->id)],
            'name' => ['required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:50'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'lms_program_id' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
