<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\User;
use App\Support\ExcelExport;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

/** Pengguna tidak pernah dihapus — hanya dinonaktifkan. */
class UserController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    public function index(Request $request)
    {
        $users = User::with(['division', 'roles'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->string('q').'%')->orWhere('email', 'like', '%'.$request->string('q').'%')))
            ->when($request->query('status') === 'nonaktif', fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function export()
    {
        $rows = User::with(['division', 'roles'])->orderBy('name')->get()->map(fn ($u) => [
            $u->name, $u->email, $u->position, $u->division?->code, $u->roleLabels(), $u->is_active ? 'Aktif' : 'Nonaktif',
            Format::date($u->access_expires_at), Format::dateTime($u->last_login_at), $u->hasTwoFactorEnabled() ? 'Ya' : 'Tidak',
        ]);

        return ExcelExport::download('Daftar Pengguna SMM', ['Nama', 'Email', 'Jabatan', 'Divisi', 'Peran', 'Status', 'Masa berlaku akun', 'Login terakhir', '2FA'], $rows);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'divisions' => Division::active()->orderBy('code')->get(), 'roles' => config('smm.roles')]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data + ['password' => $data['password'] ?? Str::password(16)]);
        $user->syncRoles($data['roles']);
        $this->log->log('buat', "Membuat pengguna {$user->name} ({$user->email}) dengan peran {$user->roleLabels()}", $user);

        return redirect()->route('admin.users.index')->with('status', "Pengguna {$user->name} dibuat.".(empty($data['password']) ? ' Minta pengguna memakai "Lupa kata sandi" untuk membuat kata sandinya.' : ''));
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'divisions' => Division::active()->orderBy('code')->get(), 'roles' => config('smm.roles')]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if ($user->id === $request->user()->id && ! in_array('gm', $data['roles'], true) && $user->hasRole('gm')) {
            return back()->withErrors(['roles' => 'Anda tidak bisa mencabut peran GM dari akun Anda sendiri.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $before = $user->roleLabels();
        $user->update($data);
        $user->syncRoles($data['roles']);
        $this->log->log('ubah', "Mengubah pengguna {$user->name}; peran: {$before} → {$user->fresh()->roleLabels()}", $user);

        return redirect()->route('admin.users.index')->with('status', 'Pengguna diperbarui.');
    }

    public function toggle(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Anda tidak bisa menonaktifkan akun Anda sendiri.']);
        }
        $user->update(['is_active' => ! $user->is_active]);
        $this->log->log($user->is_active ? 'aktifkan' : 'nonaktifkan', ($user->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." pengguna {$user->name}", $user);

        return back()->with('status', "Pengguna {$user->name} ".($user->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function resetTwoFactor(User $user)
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $this->log->log('ubah', "Mereset verifikasi dua langkah pengguna {$user->name}", $user);

        return back()->with('status', 'Verifikasi dua langkah direset.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'position' => ['nullable', 'string', 'max:255'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(array_keys(config('smm.roles')))],
            'access_expires_at' => ['nullable', 'date', 'after:today'],
            'password' => ['nullable', 'string', Password::min(10)->letters()->numbers()],
        ], [], ['roles' => 'peran', 'access_expires_at' => 'masa berlaku akun']);
        $data['email'] = Str::lower($data['email']);
        if (in_array('auditor', $data['roles'], true) && empty($data['access_expires_at'])) {
            throw ValidationException::withMessages(['access_expires_at' => 'Akun auditor wajib diberi masa berlaku.']);
        }

        return $data;
    }
}
