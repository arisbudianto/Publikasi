<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthorshipController;
use App\Http\Controllers\CatalogController as C;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CurationController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\JournalAccreditationController;
use App\Http\Controllers\JournalAgentController;
use App\Http\Controllers\JournalIdentifierController;
use App\Http\Controllers\JournalIntegrityController;
use App\Http\Controllers\JournalSourceController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\PublicationAssistantController;
use App\Http\Controllers\ReferenceCheckController;
use App\Http\Controllers\ResearchPositioningController;
use App\Http\Controllers\SemanticController;
use App\Http\Controllers\StructuredObservationImportController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TopicMonitoringController;
use App\Http\Middleware\RequireAccount;
use App\Http\Middleware\RequireAdmin;
use Illuminate\Support\Facades\Route;
use App\Models\Journal;
use App\Models\JournalSource;

Route::get('/cocokkan-ai', [SemanticController::class, 'form'])->name('semantic.form');
Route::post('/cocokkan-ai', [SemanticController::class, 'match'])->middleware('throttle:5,1')->name('semantic.run');
Route::get('/cek-referensi', [ReferenceCheckController::class, 'index'])->name('references.form');
Route::post('/cek-referensi/periksa-satu', [ReferenceCheckController::class, 'lookup'])->middleware('throttle:120,1')->name('references.lookup');
Route::get('/periksa/authorship', [AuthorshipController::class, 'index'])->name('authorship.form');
Route::post('/periksa/authorship', [AuthorshipController::class, 'analyze'])->middleware('throttle:20,1')->name('authorship.analyze');
Route::middleware(RequireAccount::class)->prefix('posisi-riset')->name('research-positioning.')->group(function () {
    Route::get('/', [ResearchPositioningController::class, 'index'])->name('form');
    Route::post('/siapkan', [ResearchPositioningController::class, 'prepare'])->middleware('throttle:4,1')->name('prepare');
    Route::post('/analisis', [ResearchPositioningController::class, 'analyze'])->middleware('throttle:4,1')->name('analyze');
});
Route::middleware(RequireAccount::class)->prefix('akun/pantauan')->name('monitor.')->group(function () {
    Route::get('/', [TopicMonitoringController::class, 'index'])->name('index');
    Route::post('/', [TopicMonitoringController::class, 'store'])->middleware('throttle:3,1')->name('store');
    Route::get('/{topic}', [TopicMonitoringController::class, 'show'])->whereNumber('topic')->name('show');
    Route::get('/{topic}/versi', [TopicMonitoringController::class, 'edit'])->whereNumber('topic')->name('edit');
    Route::post('/{topic}/versi', [TopicMonitoringController::class, 'revise'])->middleware('throttle:3,1')->whereNumber('topic')->name('revise');
    Route::post('/{topic}/temuan/{finding}/baca', [TopicMonitoringController::class, 'read'])->whereNumber('topic')->whereNumber('finding')->name('read');
    Route::delete('/{topic}', [TopicMonitoringController::class, 'destroy'])->whereNumber('topic')->name('destroy');
});
Route::get('/cocokkan', [MatchController::class, 'form'])->name('match.form');
Route::post('/cocokkan', [MatchController::class, 'match'])->middleware('throttle:20,1')->name('match.run');
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('account.index');
    }

    return view('home', [
        'sourceCount' => JournalSource::count(),
        'curatedCount' => Journal::where('status', 'published')->count(),
    ]);
})->name('home');
Route::get('/katalog-terkurasi', [C::class, 'index'])->name('catalog.index');
Route::get('/tanpa-apc', [C::class, 'noApc'])->name('catalog.no-apc');
Route::get('/periksa/jurnal', [JournalIntegrityController::class, 'index'])->name('integrity.form');
Route::post('/periksa/jurnal', [JournalIntegrityController::class, 'check'])->middleware('throttle:12,1')->name('integrity.check');
Route::get('/periksa/discontinued', [JournalIntegrityController::class, 'discontinued'])->name('integrity.discontinued');
Route::get('/bandingkan', [C::class, 'compare'])->name('catalog.compare');
Route::get('/panduan', [GuideController::class, 'index'])->name('guides.index');
Route::get('/asisten', [PublicationAssistantController::class, 'index'])->name('assistant.index');
Route::post('/asisten', [PublicationAssistantController::class, 'answer'])->middleware('throttle:4,1')->name('assistant.answer');
Route::get('/panduan/{slug}', [GuideController::class, 'show'])->name('guides.show');
Route::get('/jurnal/{journal}', [C::class, 'show'])->name('catalog.show');
Route::middleware(RequireAccount::class)->prefix('akun/usulan')->name('proposals.')->group(function () {
    Route::get('/', [ProposalController::class, 'mine'])->name('mine');
    Route::get('/baru', [ProposalController::class, 'create'])->name('create');
    Route::post('/', [ProposalController::class, 'store'])->middleware('throttle:3,60')->name('store');
    Route::post('/asisten', [ProposalController::class, 'assistantFeedback'])->middleware('throttle:3,60')->name('assistant-feedback');
});
Route::get('/admin/login', [C::class, 'loginForm'])->name('login');
Route::post('/admin/login', [C::class, 'login'])->middleware('throttle:10,1');
Route::middleware(['auth', RequireAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [C::class, 'admin'])->name('index');
    Route::get('/agen-jurnal', [JournalAgentController::class, 'index'])->name('agent.index');
    Route::post('/agen-jurnal/sumber', [JournalAgentController::class, 'store'])->name('agent.store');
    Route::post('/agen-jurnal/sumber/{source}', [JournalAgentController::class, 'toggle'])->whereNumber('source')->name('agent.toggle');
    Route::post('/agen-jurnal/kandidat/{candidate}', [JournalAgentController::class, 'review'])->whereNumber('candidate')->name('agent.review');
    Route::get('/kurasi', [CurationController::class, 'index'])->name('curation');
    Route::get('/panduan', [GuideController::class, 'admin'])->name('guides.index');
    Route::get('/panduan/baru', [GuideController::class, 'create'])->name('guides.create');
    Route::post('/panduan', [GuideController::class, 'store'])->name('guides.store');
    Route::get('/panduan/{guide}/edit', [GuideController::class, 'edit'])->whereNumber('guide')->name('guides.edit');
    Route::put('/panduan/{guide}', [GuideController::class, 'update'])->whereNumber('guide')->name('guides.update');
    Route::get('/kurasi/usulan', [ProposalController::class, 'admin'])->name('proposals.index');
    Route::get('/kurasi/usulan/{proposal}', [ProposalController::class, 'show'])->whereNumber('proposal')->name('proposals.show');
    Route::post('/kurasi/usulan/{proposal}/tinjau', [ProposalController::class, 'review'])->whereNumber('proposal')->name('proposals.review');
    Route::post('/kurasi/usulan/{proposal}/terapkan', [ProposalController::class, 'markApplied'])->whereNumber('proposal')->name('proposals.applied');
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
