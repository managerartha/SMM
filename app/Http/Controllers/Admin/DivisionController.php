<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DivisionController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    public function index()
    {
        return view('admin.divisions', ['divisions' => Division::with('head')->orderBy('code')->get(), 'users' => User::active()->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'alpha', 'max:10', 'unique:divisions,code'], 'name' => ['required', 'string', 'max:255']]);
        $d = Division::create(['code' => strtoupper($data['code']), 'name' => $data['name']]);
        $this->log->log('buat', "Menambah divisi {$d->code} – {$d->name}", $d);

        return back()->with('status', 'Divisi ditambahkan.');
    }

    public function update(Request $request, Division $division)
    {
        // Kode divisi tidak diubah karena sudah menjadi bagian nomor dokumen.
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'head_user_id' => ['nullable', Rule::exists('users', 'id')], 'is_active' => ['boolean']]);
        $division->update($data + ['is_active' => $request->boolean('is_active')]);
        $this->log->log('ubah', "Mengubah divisi {$division->code}", $division);

        return back()->with('status', 'Divisi diperbarui.');
    }
}
