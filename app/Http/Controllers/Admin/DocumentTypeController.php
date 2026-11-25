<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRule;
use App\Models\DocumentType;
use App\Models\EvidenceCategory;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    public function index()
    {
        return view('admin.document-types', ['types' => DocumentType::orderBy('sort')->get(), 'evidence' => EvidenceCategory::orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'alpha', 'max:10', 'unique:document_types,code'], 'name' => ['required', 'string', 'max:255'],
            'review_interval_months' => ['required', 'integer', 'min:1', 'max:60'], 'default_evidence_category_id' => ['nullable', 'exists:evidence_categories,id'],
        ]);
        $t = DocumentType::create($data + ['code' => strtoupper($data['code']), 'review_required' => $request->boolean('review_required'), 'sort' => DocumentType::max('sort') + 1]);
        ApprovalRule::firstOrCreate(['subject_key' => $t->approvalSubjectKey()], ['label' => $t->name, 'approver_role' => 'direktur', 'mode' => 'approve', 'phase' => 1]);
        $this->log->log('buat', "Menambah jenis dokumen {$t->code} (pengesah bawaan: Direktur, ubah di Matriks Pengesahan)", $t);

        return back()->with('status', 'Jenis dokumen ditambahkan. Periksa Matriks Pengesahan untuk jenis baru ini.');
    }

    public function update(Request $request, DocumentType $documentType)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'review_interval_months' => ['required', 'integer', 'min:1', 'max:60'],
            'default_evidence_category_id' => ['nullable', 'exists:evidence_categories,id'],
        ]);
        $documentType->update($data + ['review_required' => $request->boolean('review_required'), 'is_active' => $request->boolean('is_active')]);
        $this->log->log('ubah', "Mengubah jenis dokumen {$documentType->code}", $documentType);

        return back()->with('status', 'Jenis dokumen diperbarui.');
    }
}
