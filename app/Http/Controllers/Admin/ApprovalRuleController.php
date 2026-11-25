<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApprovalRuleController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    public function index()
    {
        return view('admin.approval-rules', ['rules' => ApprovalRule::orderBy('phase')->orderBy('id')->get(), 'roles' => config('smm.roles')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['rules' => ['required', 'array'], 'rules.*' => ['required', Rule::in(array_keys(config('smm.roles')))]]);
        $changes = [];
        foreach ($data['rules'] as $id => $role) {
            $rule = ApprovalRule::find($id);
            if ($rule && $rule->approver_role !== $role) {
                $changes[] = "{$rule->label}: {$rule->approver_role} → {$role}";
                $rule->update(['approver_role' => $role, 'updated_by' => $request->user()->id]);
            }
        }
        if ($changes) {
            $this->log->log('pengaturan', 'Mengubah matriks pengesahan: '.implode('; ', $changes), null, ['changes' => $changes]);
        }

        return back()->with('status', $changes ? 'Matriks pengesahan diperbarui.' : 'Tidak ada perubahan.');
    }
}
