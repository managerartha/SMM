<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    public function index()
    {
        return view('admin.roles', [
            'roles' => Role::with('permissions')->get()->sortBy(fn ($r) => array_search($r->name, array_keys(config('smm.roles')))),
            'permissions' => config('smm.permissions'),
            'labels' => config('smm.roles'),
        ]);
    }

    public function update(Request $request)
    {
        $input = $request->input('perm', []);
        $valid = array_keys(config('smm.permissions'));
        $changes = [];
        foreach (Role::all() as $role) {
            if ($role->name === 'gm') {
                continue; // GM selalu memegang semua izin (admin sistem).
            }
            $new = array_values(array_intersect($valid, array_keys($input[$role->name] ?? [])));
            $old = $role->permissions->pluck('name')->sort()->values()->all();
            sort($new);
            if ($new !== $old) {
                $role->syncPermissions($new);
                $changes[] = $role->name.': +'.implode(',', array_diff($new, $old)).' -'.implode(',', array_diff($old, $new));
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if ($changes) {
            $this->log->log('pengaturan', 'Mengubah izin peran: '.implode('; ', $changes));
        }

        return back()->with('status', $changes ? 'Izin peran diperbarui.' : 'Tidak ada perubahan.');
    }
}
