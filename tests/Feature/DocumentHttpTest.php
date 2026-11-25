<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\ExternalDocumentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Alur lengkap lewat formulir web, termasuk halaman yang dirender. */
class DocumentHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_alur_lewat_formulir_web(): void
    {
        Notification::fake();
        $this->seedBase();
        $gm = $this->makeUser('gm');
        $kadiv = $this->makeUser('kadiv', 'OPS');
        $dir = $this->makeUser('direktur');

        $this->actingAs($gm)->get(route('documents.create'))->assertOk();
        $res = $this->actingAs($gm)->post(route('documents.store'), [
            'document_type_id' => DocumentType::where('code', 'SOP')->value('id'),
            'division_id' => Division::where('code', 'OPS')->value('id'),
            'title' => 'SOP Uji Web', 'summary' => 'Ringkasan', 'access_scope' => 'umum',
            'pdf' => $this->pdf(),
        ]);
        $doc = Document::firstOrFail();
        $res->assertRedirect(route('documents.show', $doc));
        $rev = $doc->revisions()->first();

        $this->actingAs($gm)->get(route('documents.show', $doc))->assertOk()->assertSee('ASI-SOP-OPS-01')->assertSee('Ajukan untuk review');
        $this->actingAs($gm)->post(route('documents.submit', [$doc, $rev]), ['reviewer_id' => $kadiv->id])->assertSessionHasNoErrors();
        $this->actingAs($kadiv)->get(route('documents.show', $doc))->assertSee('Keputusan review');
        $this->actingAs($kadiv)->post(route('documents.review', [$doc, $rev]), ['decision' => 'tolak', 'note' => ''])->assertSessionHasErrors('note');
        $this->actingAs($kadiv)->post(route('documents.review', [$doc, $rev]), ['decision' => 'teruskan'])->assertSessionHasNoErrors();
        $this->actingAs($dir)->get(route('dashboard'))->assertOk()->assertSee('ASI-SOP-OPS-01');
        $this->actingAs($dir)->post(route('documents.approve', [$doc, $rev]), ['decision' => 'sahkan'])->assertSessionHasNoErrors();
        $this->assertSame('berlaku', $rev->fresh()->status);

        // Halaman lain dirender tanpa galat.
        foreach (['documents.index', 'external-documents.index', 'activity.index', 'search', 'profile', 'notifications.index',
            'admin.users.index', 'admin.users.create', 'admin.divisions.index', 'admin.document-types.index', 'admin.external-categories.index',
            'admin.approval-rules.index', 'admin.roles.index', 'admin.settings.index', 'admin.drive.index', 'admin.backup.index', 'admin.system.index'] as $r) {
            $this->actingAs($gm)->get(route($r, $r === 'search' ? ['q' => 'SOP'] : []))->assertOk();
        }
        $this->actingAs($gm)->get(route('search', ['q' => 'Uji Web']))->assertSee('ASI-SOP-OPS-01');
        $this->actingAs($gm)->get(route('documents.export'))->assertOk()->assertHeader('content-disposition');

        // Legalitas lewat formulir
        $this->actingAs($gm)->post(route('external-documents.store'), [
            'external_document_category_id' => ExternalDocumentCategory::where('code', 'NIB')->value('id'),
            'title' => 'NIB', 'issuer' => 'OSS', 'original_number' => '9120001234567',
            'issued_at' => '2024-01-10', 'expires_at' => '', 'file' => $this->pdf(),
        ])->assertSessionHasNoErrors();
        $this->actingAs($gm)->get(route('external-documents.show', 1))->assertOk()->assertSee('DE-NIB-001');
    }

    public function test_pengaturan_dan_matriks_bisa_diubah_gm(): void
    {
        $this->seedBase();
        $gm = $this->makeUser('gm');
        $this->actingAs($gm)->put(route('admin.settings.update'), [
            'upload_max_mb' => 25, 'reminder_external_days' => '120, 60, 30', 'reminder_external_soon_days' => 90,
            'reminder_review_days' => 30, 'backup_daily_retention' => 30, 'backup_monthly_retention' => 12,
            'patterns' => ['numbering.document' => 'ASI-{jenis}-{divisi}-{urut:3}'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(25, \App\Support\Settings::get('upload.max_mb'));
        $this->assertSame([120, 60, 30], \App\Support\Settings::get('reminder.external_days'));
        $this->assertSame('ASI-SOP-QA-001', app(\App\Domain\Numbering\NumberGenerator::class)->next('document', ['jenis' => 'SOP', 'divisi' => 'QA']));

        $rule = \App\Models\ApprovalRule::where('subject_key', 'document:IK')->first();
        $this->actingAs($gm)->put(route('admin.approval-rules.update'), ['rules' => [$rule->id => 'mr']])->assertSessionHasNoErrors();
        $this->assertSame('mr', $rule->fresh()->approver_role);
    }
}
