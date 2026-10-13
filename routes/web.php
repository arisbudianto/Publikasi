<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthorshipController;
use App\Http\Controllers\CatalogController as C;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CurationController;
use App\Http\Controllers\JournalAccreditationController;
use App\Http\Controllers\JournalIdentifierController;
use App\Http\Controllers\JournalIntegrityController;
use App\Http\Controllers\JournalSourceController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReferenceCheckController;
use App\Http\Controllers\SemanticController;
use App\Http\Controllers\StructuredObservationImportController;
use App\Http\Controllers\SubmissionController;
use App\Http\Middleware\RequireAccount;
use App\Http\Middleware\RequireAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/cocokkan-ai', [SemanticController::class, 'form'])->name('semantic.form');
Route::post('/cocokkan-ai', [SemanticController::class, 'match'])->middleware('throttle:5,1')->name('semantic.run');
Route::get('/cek-referensi', [ReferenceCheckController::class, 'index'])->name('references.form');
Route::post('/cek-referensi/periksa-satu', [ReferenceCheckController::class, 'lookup'])->middleware('throttle:120,1')->name('references.lookup');
Route::get('/periksa/authorship', [AuthorshipController::class, 'index'])->name('authorship.form');
Route::post('/periksa/authorship', [AuthorshipController::class, 'analyze'])->middleware('throttle:20,1')->name('authorship.analyze');
Route::get('/cocokkan', [MatchController::class, 'form'])->name('match.form');
Route::post('/cocokkan', [MatchController::class, 'match'])->middleware('throttle:20,1')->name('match.run');
Route::get('/', [JournalSourceController::class, 'index'])->name('sources.home');
Route::get('/katalog-terkurasi', [C::class, 'index'])->name('catalog.index');
Route::get('/tanpa-apc', [C::class, 'noApc'])->name('catalog.no-apc');
Route::get('/periksa/jurnal', [JournalIntegrityController::class, 'index'])->name('integrity.form');
Route::post('/periksa/jurnal', [JournalIntegrityController::class, 'check'])->middleware('throttle:12,1')->name('integrity.check');
Route::get('/periksa/discontinued', [JournalIntegrityController::class, 'discontinued'])->name('integrity.discontinued');
Route::get('/bandingkan', [C::class, 'compare'])->name('catalog.compare');
Route::get('/jurnal/{journal}', [C::class, 'show'])->name('catalog.show');
Route::get('/admin/login', [C::class, 'loginForm'])->name('login');
Route::post('/admin/login', [C::class, 'login'])->middleware('throttle:10,1');
Route::middleware(['auth', RequireAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [C::class, 'admin'])->name('index');
    Route::get('/kurasi', [CurationController::class, 'index'])->name('curation');
    Route::get('/kurasi/observasi/import', [StructuredObservationImportController::class, 'form'])->name('observation-import.form');
    Route::post('/kurasi/observasi/import/preview', [StructuredObservationImportController::class, 'preview'])->middleware('throttle:10,1')->name('observation-import.preview');
    Route::post('/kurasi/observasi/import/commit', [StructuredObservationImportController::class, 'commit'])->middleware('throttle:5,1')->name('observation-import.commit');
    Route::get('/kurasi/observasi/import/template', [StructuredObservationImportController::class, 'template'])->name('observation-import.template');
    Route::post('/kurasi/{journal}/identifier', [JournalIdentifierController::class, 'store'])->name('identifier.store');
    Route::post('/kurasi/{journal}/identifier/{identifier}/tarik', [JournalIdentifierController::class, 'retire'])->whereNumber('identifier')->name('identifier.retire');
    Route::post('/kurasi/{journal}/artikel/refresh', [CurationController::class, 'refreshArticles'])->middleware('throttle:5,1')->name('articles.refresh');
    Route::get('/kurasi/{journal}', [CurationController::class, 'show'])->name('review');
    Route::post('/kurasi/{journal}/observasi', [CurationController::class, 'publish'])->name('observation.publish');
    Route::post('/kurasi/{journal}/observasi/{observation}/tarik', [CurationController::class, 'retire'])->name('observation.retire');
    Route::post('/logout', [C::class, 'logout'])->name('logout');
    Route::get('/jurnal/create', [C::class, 'create'])->name('create');
    Route::post('/jurnal', [C::class, 'store'])->name('store');
    Route::get('/jurnal/{journal}/edit', [C::class, 'edit'])->name('edit');
    Route::put('/jurnal/{journal}', [C::class, 'update'])->name('update');
    Route::post('/jurnal/{journal}/archive', [C::class, 'archive'])->name('archive');
    Route::get('/import', [C::class, 'importForm'])->name('import');
    Route::post('/import/preview', [C::class, 'preview'])->name('preview');
    Route::post('/import/commit', [C::class, 'commitImport'])->name('commit');
    Route::get('/import/template', [C::class, 'template'])->name('template');
});

Route::get('/akun/login', [AccountController::class, 'loginForm'])->name('account.login');
Route::post('/akun/login', [AccountController::class, 'login'])->middleware('throttle:10,1');
Route::get('/akun/daftar', [AccountController::class, 'registerForm'])->name('account.register');
Route::post('/akun/daftar', [AccountController::class, 'register'])->middleware('throttle:3,1');
Route::middleware(RequireAccount::class)->prefix('akun')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::post('/keluar', [AccountController::class, 'logout'])->name('logout');
    Route::post('/shortlist/{journal}', [AccountController::class, 'saveJournal'])->name('save');
    Route::delete('/shortlist/{shortlist}', [AccountController::class, 'removeJournal'])->name('remove');
    Route::delete('/riwayat/{history}', [AccountController::class, 'removeHistory'])->name('history.remove');
    Route::delete('/integritas/{assessment}', [JournalIntegrityController::class, 'removeAssessment'])->whereNumber('assessment')->name('integrity.remove');
    Route::put('/profil', [AccountController::class, 'profile'])->name('profile');
    Route::put('/password', [AccountController::class, 'password'])->middleware('throttle:5,1')->name('password');
});

Route::get('/jurnal/{journal}/checklist', [ChecklistController::class, 'show'])->name('checklist.show');
Route::post('/akun/checklist/{journal}', [ChecklistController::class, 'save'])->middleware([RequireAccount::class, 'throttle:20,1'])->name('checklist.save');

Route::middleware(RequireAccount::class)->prefix('akun/submission')->name('submissions.')->group(function () {
    Route::get('/', [SubmissionController::class, 'index'])->name('index');
    Route::post('/', [SubmissionController::class, 'store'])->middleware('throttle:20,1')->name('store');
    Route::get('/{submission}', [SubmissionController::class, 'show'])->whereNumber('submission')->name('show');
    Route::put('/{submission}', [SubmissionController::class, 'update'])->middleware('throttle:30,1')->name('update');
    Route::get('/{submission}/matriks.csv', [SubmissionController::class, 'export'])->middleware('throttle:10,1')->name('export');
    Route::post('/{submission}/respons', [SubmissionController::class, 'responseStore'])->middleware('throttle:30,1')->name('responses.store');
    Route::put('/{submission}/respons/{response}', [SubmissionController::class, 'responseUpdate'])->middleware('throttle:30,1')->name('responses.update');
});

Route::get('/layanan', [OperationsController::class, 'status'])->name('service.status');
Route::middleware(RequireAccount::class)->prefix('akun/pembayaran')->name('payments.')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->name('index');
    Route::post('/', [PaymentController::class, 'store'])->middleware('throttle:5,1')->name('store');
    Route::post('/{payment}/batal', [PaymentController::class, 'cancel'])->middleware('throttle:10,1')->name('cancel');
});
Route::middleware(['auth', RequireAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/operasional', [OperationsController::class, 'index'])->middleware('throttle:10,1')->name('operations');
    Route::get('/pembayaran', [PaymentController::class, 'admin'])->name('payments');
    Route::post('/pembayaran/{payment}/verifikasi', [PaymentController::class, 'review'])->middleware('throttle:10,1')->name('payments.review');
});

Route::get('/sumber-jurnal', [JournalSourceController::class, 'index'])->name('sources.index');
Route::get('/sumber-jurnal/{source}', [JournalSourceController::class, 'show'])->name('sources.show');
Route::get('/akreditasi-sinta', [JournalAccreditationController::class, 'index'])->name('accreditations.index');
Route::middleware(['auth', RequireAdmin::class])->prefix('admin/sumber-jurnal')->name('admin.sources.')->group(function () {
    Route::get('/{source}', [JournalSourceController::class, 'prepare'])->name('prepare');
    Route::post('/{source}/draft', [JournalSourceController::class, 'promote'])->middleware('throttle:10,1')->name('promote');
});
