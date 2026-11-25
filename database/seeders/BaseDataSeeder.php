<?php

namespace Database\Seeders;

use App\Models\ApprovalRule;
use App\Models\Division;
use App\Models\DocumentType;
use App\Models\EvidenceCategory;
use App\Models\ExternalDocumentCategory;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Data dasar yang WAJIB ada (juga dijalankan installer di produksi). Aman dijalankan berulang:
 * hanya menambah yang belum ada, tidak menimpa perubahan yang dibuat GM lewat layar Admin.
 */
class BaseDataSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(config('smm.permissions')) as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        foreach (config('smm.role_permissions') as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
                $role->syncPermissions($perms === ['*'] ? array_keys(config('smm.permissions')) : $perms);
            } elseif ($perms === ['*']) {
                // GM selalu memegang semua izin, termasuk izin baru dari tahap berikutnya.
                $role->syncPermissions(array_keys(config('smm.permissions')));
            }
        }

        foreach (config('smm.evidence_categories') as $id => [$name, $folder]) {
            EvidenceCategory::firstOrCreate(['id' => $id], ['name' => $name, 'folder_name' => $folder]);
        }

        foreach (config('smm.divisions') as $code => $name) {
            Division::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (config('smm.document_types') as $i => [$code, $name, $review, $months, $evidence]) {
            DocumentType::firstOrCreate(['code' => $code], [
                'name' => $name, 'review_required' => $review, 'review_interval_months' => $months,
                'default_evidence_category_id' => $evidence, 'sort' => $i,
            ]);
        }

        foreach (config('smm.external_categories') as $i => [$code, $name, $evidence]) {
            ExternalDocumentCategory::firstOrCreate(['code' => $code], ['name' => $name, 'default_evidence_category_id' => $evidence, 'sort' => $i]);
        }

        foreach (config('smm.approval_rules') as [$key, $label, $role, $mode, $phase]) {
            ApprovalRule::firstOrCreate(['subject_key' => $key], ['label' => $label, 'approver_role' => $role, 'mode' => $mode, 'phase' => $phase]);
        }

        Settings::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
