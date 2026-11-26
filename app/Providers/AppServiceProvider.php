<?php

namespace App\Providers;

use App\Domain\Drive\DriveGateway;
use App\Domain\Drive\GoogleDriveGateway;
use App\Domain\Drive\GoogleOAuth;
use App\Domain\Drive\LocalDriveGateway;
use App\Listeners\LogAuthEvents;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\ExternalDocument;
use App\Models\ExternalDocumentVersion;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use App\Policies\DocumentPolicy;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleOAuth::class, fn () => new GoogleOAuth(
            (string) config('smm.drive.client_id'),
            (string) config('smm.drive.client_secret'),
            config('smm.drive.token_path'),
        ));

        $this->app->singleton(DriveGateway::class, function ($app) {
            return config('smm.drive.driver') === 'google'
                ? new GoogleDriveGateway($app->make(GoogleOAuth::class))
                : new LocalDriveGateway(config('smm.drive.local_root'));
        });
    }

    public function boot(): void
    {
        Paginator::useTailwind();
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Nama tipe polimorfik yang stabil (tidak bergantung nama kelas PHP).
        Relation::enforceMorphMap([
            'user' => User::class,
            'document' => Document::class,
            'document_revision' => DocumentRevision::class,
            'external_document' => ExternalDocument::class,
            'external_document_version' => ExternalDocumentVersion::class,
            'complaint' => \App\Models\Complaint::class,
            'ncr' => \App\Models\Ncr::class,
            'ncr_action' => \App\Models\NcrAction::class,
            'ncr_effectiveness_check' => \App\Models\NcrEffectivenessCheck::class,
            'risk' => \App\Models\Risk::class,
            'quality_record' => \App\Models\QualityRecord::class,
            'personnel' => \App\Models\Personnel::class,
            'personnel_document' => \App\Models\PersonnelDocument::class,

            'facility' => \App\Models\Facility::class,
            'facility_log' => \App\Models\FacilityLog::class,
            'supplier' => \App\Models\Supplier::class,
            'lms_import' => \App\Models\LmsImport::class,
            'kpi' => \App\Models\Kpi::class,
            'audit_program' => \App\Models\AuditProgram::class,
            'audit' => \App\Models\Audit::class,
            'management_review' => \App\Models\ManagementReview::class,
            'review_action' => \App\Models\ReviewAction::class,
            'innovation' => \App\Models\Innovation::class,
            'performance_report' => \App\Models\PerformanceReport::class,
            'evidence_package' => \App\Models\EvidencePackage::class,
        ]);

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DocumentRevision::class, DocumentPolicy::class);
        Gate::policy(\App\Models\Complaint::class, \App\Policies\ComplaintPolicy::class);
        Gate::policy(\App\Models\Ncr::class, \App\Policies\NcrPolicy::class);
        Gate::policy(\App\Models\NcrAction::class, \App\Policies\NcrPolicy::class);
        Gate::policy(\App\Models\Risk::class, \App\Policies\RiskPolicy::class);

        Event::listen(Login::class, [LogAuthEvents::class, 'login']);
        Event::listen(Failed::class, [LogAuthEvents::class, 'failed']);
        Event::listen(Logout::class, [LogAuthEvents::class, 'logout']);
    }
}
