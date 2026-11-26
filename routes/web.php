<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AttachmentController;
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
