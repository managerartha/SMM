<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\EvidencePackageController;
use App\Http\Controllers\InnovationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\NcrController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\Admin\ApprovalRuleController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\DocumentTypeController;
use App\Http\Controllers\Admin\DriveController;
use App\Http\Controllers\Admin\ExternalCategoryController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExternalDocumentController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

// Installer web (sekali pakai, butuh SMM_INSTALL_TOKEN di .env).
Route::get('/install', [InstallController::class, 'show'])->name('install');
Route::post('/install', [InstallController::class, 'run'])->middleware('throttle:5,1')->name('install.run');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/cari', SearchController::class)->name('search');

    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifikasi/{id}', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('/profil', [ProfileController::class, 'show'])->name('profile');

    // Dokumen Terkendali
    Route::prefix('dokumen')->name('documents.')->controller(DocumentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{document}', 'show')->name('show');
        Route::get('/{document}/revisi/{revision}/edit', 'edit')->name('edit');
        Route::put('/{document}/revisi/{revision}', 'update')->name('update');
        Route::post('/{document}/revisi/{revision}/ajukan', 'submit')->name('submit');
        Route::post('/{document}/revisi/{revision}/review', 'review')->name('review');
        Route::post('/{document}/revisi/{revision}/sahkan', 'approve')->name('approve');
        Route::post('/{document}/revisi/{revision}/batal', 'cancel')->name('cancel');
        Route::get('/{document}/revisi/{revision}/unduh/{kind}', 'download')->whereIn('kind', ['pdf', 'sumber'])->name('download');
        Route::get('/{document}/revisi-baru', 'createRevision')->name('revisions.create');
        Route::post('/{document}/revisi-baru', 'storeRevision')->name('revisions.store');
        Route::post('/{document}/arsip', 'archive')->name('archive');
        Route::post('/{document}/pulihkan', 'restore')->name('restore');
    });

    // Legalitas & Kontrak
    Route::prefix('legalitas')->name('external-documents.')->controller(ExternalDocumentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{externalDocument}', 'show')->name('show');
        Route::get('/{externalDocument}/edit', 'edit')->name('edit');
        Route::put('/{externalDocument}', 'update')->name('update');
        Route::get('/{externalDocument}/perpanjang', 'renewForm')->name('renew');
        Route::post('/{externalDocument}/perpanjang', 'renew')->name('renew.store');
        Route::post('/{externalDocument}/versi/{version}/verifikasi', 'verify')->name('verify');
        Route::get('/{externalDocument}/versi/{version}/unduh', 'download')->name('download');
        Route::post('/{externalDocument}/arsip', 'archive')->name('archive');
        Route::post('/{externalDocument}/pulihkan', 'restore')->name('restore');
    });

    // Tahap 2 – Keluhan & Feedback
    Route::prefix('keluhan')->name('complaints.')->controller(ComplaintController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{complaint}', 'show')->name('show');
        Route::get('/{complaint}/edit', 'edit')->name('edit');
        Route::put('/{complaint}', 'update')->name('update');
        Route::post('/{complaint}/tugaskan', 'assign')->name('assign');
        Route::post('/{complaint}/tanggapi', 'respond')->name('respond');
        Route::post('/{complaint}/batal', 'cancel')->name('cancel');
        Route::post('/{complaint}/jadikan-ncr', 'escalate')->name('escalate');
    });

    // Tahap 2 – NCR & CAPA
    Route::prefix('ncr')->name('ncrs.')->controller(NcrController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{ncr}', 'show')->name('show');
        Route::get('/{ncr}/cetak', 'print')->name('print');
        Route::put('/{ncr}', 'update')->name('update');
        Route::post('/{ncr}/analisis', 'analysis')->name('analysis');
        Route::post('/{ncr}/tindakan', 'addAction')->name('actions.store');
        Route::post('/{ncr}/mulai', 'start')->name('start');
        Route::post('/{ncr}/tindakan/{action}/selesai', 'completeAction')->name('actions.complete');
        Route::post('/{ncr}/verifikasi', 'verify')->name('verify');
        Route::post('/{ncr}/efektivitas/{check}', 'effectiveness')->name('effectiveness');
        Route::post('/{ncr}/tutup', 'close')->name('close');
        Route::post('/{ncr}/batal', 'cancel')->name('cancel');
    });

    // Tahap 2 – Risiko & Peluang
    Route::prefix('risiko')->name('risks.')->controller(RiskController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{risk}', 'show')->name('show');
        Route::get('/{risk}/edit', 'edit')->name('edit');
        Route::put('/{risk}', 'update')->name('update');
        Route::post('/{risk}/nilai-ulang', 'reassess')->name('reassess');
        Route::post('/{risk}/tutup', 'close')->name('close');
        Route::post('/{risk}/buka', 'reopen')->name('reopen');
    });

    // Tahap 3 – Kelas (LMS) & impor
    Route::prefix('kelas')->name('classes.')->controller(ClassController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/impor', 'importForm')->name('import');
        Route::post('/impor', 'import')->name('import.run');
        Route::post('/sinkron', 'sync')->name('sync');
        Route::get('/template/{kind}', 'template')->whereIn('kind', ['kelas', 'survei'])->name('template');
        Route::get('/{class}', 'show')->name('show');
    });

    Route::prefix('catatan-mutu')->name('records.')->controller(RecordController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{record}', 'show')->name('show');
        Route::post('/{record}/berkas', 'addFiles')->name('files');
        Route::post('/{record}/arsip', 'archive')->name('archive');
    });

    Route::prefix('personel')->name('personnel.')->controller(PersonnelController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{personnel}', 'show')->name('show');
        Route::get('/{personnel}/edit', 'edit')->name('edit');
        Route::put('/{personnel}', 'update')->name('update');
        Route::post('/{personnel}/berkas', 'storeDocument')->name('documents.store');
        Route::post('/{personnel}/berkas/{document}/verifikasi', 'verifyDocument')->name('documents.verify');
        Route::get('/{personnel}/berkas/{document}/unduh', 'downloadDocument')->name('documents.download');
    });

    Route::prefix('sarana')->name('facilities.')->controller(FacilityController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{facility}', 'show')->name('show');
        Route::get('/{facility}/edit', 'edit')->name('edit');
        Route::put('/{facility}', 'update')->name('update');
        Route::post('/{facility}/riwayat', 'storeLog')->name('logs.store');
    });

    Route::prefix('survei')->name('surveys.')->controller(SurveyController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::post('/{response}/jadikan-keluhan', 'toComplaint')->name('complaint');
    });

    Route::prefix('kpi')->name('kpis.')->controller(KpiController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/hitung', 'calculate')->name('calculate');
        Route::get('/{kpi}', 'show')->name('show');
        Route::get('/{kpi}/edit', 'edit')->name('edit');
        Route::put('/{kpi}', 'update')->name('update');
        Route::post('/{kpi}/realisasi', 'storeValue')->name('values.store');
    });

    Route::prefix('pemasok')->name('suppliers.')->controller(SupplierController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{supplier}', 'show')->name('show');
        Route::get('/{supplier}/edit', 'edit')->name('edit');
        Route::put('/{supplier}', 'update')->name('update');
        Route::post('/{supplier}/evaluasi', 'evaluate')->name('evaluate');
    });

    // Tahap 4 – Audit internal
    Route::prefix('audit')->name('audits.')->controller(AuditController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/program', 'storeProgram')->name('programs.store');
        Route::get('/program/{program}', 'showProgram')->name('programs.show');
        Route::put('/program/{program}', 'updateProgram')->name('programs.update');
        Route::post('/program/{program}/ajukan', 'submitProgram')->name('programs.submit');
        Route::post('/program/{program}/sahkan', 'decideProgram')->name('programs.decide');
        Route::post('/program/{program}/jadwal', 'schedule')->name('schedule');
        Route::get('/{audit}', 'show')->name('show');
        Route::post('/{audit}/checklist', 'checklist')->name('checklist');
        Route::post('/{audit}/temuan', 'finding')->name('finding');
        Route::post('/{audit}/laporan', 'report')->name('report');
        Route::post('/{audit}/sahkan', 'decide')->name('decide');
        Route::post('/{audit}/batal', 'cancel')->name('cancel');
    });

    Route::prefix('tinjauan-manajemen')->name('reviews.')->controller(ReviewController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{review}', 'show')->name('show');
        Route::put('/{review}', 'update')->name('update');
        Route::post('/{review}/isi-otomatis', 'refresh')->name('refresh');
        Route::post('/{review}/tindak-lanjut', 'addAction')->name('actions.store');
        Route::post('/{review}/tindak-lanjut/{action}/selesai', 'completeAction')->name('actions.complete');
        Route::post('/{review}/ajukan', 'submit')->name('submit');
        Route::post('/{review}/sahkan', 'decide')->name('decide');
    });

    Route::prefix('inovasi')->name('innovations.')->controller(InnovationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/ekspor', 'export')->name('export');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{innovation}', 'show')->name('show');
        Route::get('/{innovation}/edit', 'edit')->name('edit');
        Route::put('/{innovation}', 'update')->name('update');
        Route::post('/{innovation}/putuskan', 'decide')->name('decide');
        Route::post('/{innovation}/mulai', 'start')->name('start');
        Route::post('/{innovation}/bukti', 'evidence')->name('evidence');
        Route::post('/{innovation}/selesai', 'complete')->name('complete');
    });

    Route::prefix('laporan-kinerja')->name('reports.')->controller(ReportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{report}', 'show')->name('show');
        Route::post('/{report}/perbarui', 'regenerate')->name('regenerate');
        Route::post('/{report}/analisis', 'analysis')->name('analysis');
        Route::post('/{report}/ajukan', 'submit')->name('submit');
        Route::post('/{report}/sahkan', 'decide')->name('decide');
    });

    Route::get('/paket-bukti', [EvidencePackageController::class, 'index'])->name('evidence.index');
    Route::post('/paket-bukti', [EvidencePackageController::class, 'store'])->name('evidence.store');

    Route::get('/lampiran/{uuid}', AttachmentController::class)->whereUuid('uuid')->name('attachments.download');

    Route::middleware('permission:log.lihat')->group(function () {
        Route::get('/log-aktivitas', [ActivityLogController::class, 'index'])->name('activity.index');
        Route::get('/log-aktivitas/ekspor', [ActivityLogController::class, 'export'])->name('activity.export');
    });

    // Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permission:pengguna.kelola')->group(function () {
            Route::get('/pengguna/ekspor', [UserController::class, 'export'])->name('users.export');
            Route::resource('pengguna', UserController::class)->parameters(['pengguna' => 'user'])->names('users')->except(['show', 'destroy']);
            Route::post('/pengguna/{user}/status', [UserController::class, 'toggle'])->name('users.toggle');
            Route::post('/pengguna/{user}/reset-2fa', [UserController::class, 'resetTwoFactor'])->name('users.reset-2fa');
        });

        Route::middleware('permission:master.kelola')->group(function () {
            Route::get('/divisi', [DivisionController::class, 'index'])->name('divisions.index');
            Route::post('/divisi', [DivisionController::class, 'store'])->name('divisions.store');
            Route::put('/divisi/{division}', [DivisionController::class, 'update'])->name('divisions.update');
            Route::get('/jenis-dokumen', [DocumentTypeController::class, 'index'])->name('document-types.index');
            Route::post('/jenis-dokumen', [DocumentTypeController::class, 'store'])->name('document-types.store');
            Route::put('/jenis-dokumen/{documentType}', [DocumentTypeController::class, 'update'])->name('document-types.update');
            Route::get('/kategori-eksternal', [ExternalCategoryController::class, 'index'])->name('external-categories.index');
            Route::post('/kategori-eksternal', [ExternalCategoryController::class, 'store'])->name('external-categories.store');
            Route::put('/kategori-eksternal/{category}', [ExternalCategoryController::class, 'update'])->name('external-categories.update');
            Route::get('/program-pelatihan', [ProgramController::class, 'index'])->name('programs.index');
            Route::post('/program-pelatihan', [ProgramController::class, 'store'])->name('programs.store');
            Route::put('/program-pelatihan/{program}', [ProgramController::class, 'update'])->name('programs.update');
        });

        Route::middleware('permission:pengaturan.kelola')->group(function () {
            Route::get('/matriks-pengesahan', [ApprovalRuleController::class, 'index'])->name('approval-rules.index');
            Route::put('/matriks-pengesahan', [ApprovalRuleController::class, 'update'])->name('approval-rules.update');
            Route::get('/peran-izin', [RolePermissionController::class, 'index'])->name('roles.index');
            Route::put('/peran-izin', [RolePermissionController::class, 'update'])->name('roles.update');
            Route::get('/pengaturan', [SettingController::class, 'index'])->name('settings.index');
            Route::put('/pengaturan', [SettingController::class, 'update'])->name('settings.update');
            Route::get('/pembaruan', [SystemUpdateController::class, 'index'])->name('system.index');
            Route::post('/pembaruan', [SystemUpdateController::class, 'run'])->middleware('password.confirm')->name('system.run');
            Route::get('/google-drive', [DriveController::class, 'index'])->name('drive.index');
            Route::post('/google-drive/hubungkan', [DriveController::class, 'connect'])->middleware('password.confirm')->name('drive.connect');
            Route::get('/google-drive/callback', [DriveController::class, 'callback'])->name('drive.callback');
            Route::post('/google-drive/uji', [DriveController::class, 'test'])->name('drive.test');
            Route::post('/google-drive/putuskan', [DriveController::class, 'disconnect'])->middleware('password.confirm')->name('drive.disconnect');
        });

        Route::middleware('permission:backup.kelola')->group(function () {
            Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
            Route::post('/backup', [BackupController::class, 'run'])->name('backup.run');
        });
    });
});
