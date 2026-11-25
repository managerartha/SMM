<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Activity\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\EvidenceCategory;
use App\Models\ExternalDocumentCategory;
use Illuminate\Http\Request;

class ExternalCategoryController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    public function index()
    {
        return view('admin.external-categories', ['categories' => ExternalDocumentCategory::orderBy('sort')->get(), 'evidence' => EvidenceCategory::orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'alpha', 'max:10', 'unique:external_document_categories,code'], 'name' => ['required', 'string', 'max:255'], 'default_evidence_category_id' => ['nullable', 'exists:evidence_categories,id']]);
        $c = ExternalDocumentCategory::create($data + ['code' => strtoupper($data['code']), 'sort' => ExternalDocumentCategory::max('sort') + 1]);
        $this->log->log('buat', "Menambah kategori dokumen eksternal {$c->code}", $c);

        return back()->with('status', 'Kategori ditambahkan.');
    }

    public function update(Request $request, ExternalDocumentCategory $category)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'default_evidence_category_id' => ['nullable', 'exists:evidence_categories,id']]);
        $category->update($data + ['is_active' => $request->boolean('is_active')]);
        $this->log->log('ubah', "Mengubah kategori dokumen eksternal {$category->code}", $category);

        return back()->with('status', 'Kategori diperbarui.');
    }
}
