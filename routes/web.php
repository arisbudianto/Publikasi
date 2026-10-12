<?php

use App\Http\Controllers\CatalogController as C;
use App\Http\Middleware\RequireAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/cocokkan-ai', [\App\Http\Controllers\SemanticController::class, 'form'])->name('semantic.form');
Route::post('/cocokkan-ai', [\App\Http\Controllers\SemanticController::class, 'match'])->middleware('throttle:5,1')->name('semantic.run');
Route::get('/cocokkan', [\App\Http\Controllers\MatchController::class, 'form'])->name('match.form');
Route::post('/cocokkan', [\App\Http\Controllers\MatchController::class, 'match'])->middleware('throttle:20,1')->name('match.run');
Route::get('/', [C::class, 'index'])->name('catalog.index');
Route::get('/bandingkan', [C::class, 'compare'])->name('catalog.compare');
Route::get('/jurnal/{journal}', [C::class, 'show'])->name('catalog.show');
Route::get('/admin/login', [C::class, 'loginForm'])->name('login');
Route::post('/admin/login', [C::class, 'login'])->middleware('throttle:10,1');
Route::middleware(['auth', RequireAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [C::class, 'admin'])->name('index');
    Route::get('/kurasi', [\App\Http\Controllers\CurationController::class, 'index'])->name('curation');
    Route::post('/kurasi/{journal}/artikel/refresh', [\App\Http\Controllers\CurationController::class, 'refreshArticles'])->middleware('throttle:5,1')->name('articles.refresh');
    Route::get('/kurasi/{journal}', [\App\Http\Controllers\CurationController::class, 'show'])->name('review');
    Route::post('/kurasi/{journal}/observasi', [\App\Http\Controllers\CurationController::class, 'publish'])->name('observation.publish');
    Route::post('/kurasi/{journal}/observasi/{observation}/tarik', [\App\Http\Controllers\CurationController::class, 'retire'])->name('observation.retire');
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

Route::get('/akun/login', [\App\Http\Controllers\AccountController::class,'loginForm'])->name('account.login');
Route::post('/akun/login', [\App\Http\Controllers\AccountController::class,'login'])->middleware('throttle:10,1');
Route::get('/akun/daftar', [\App\Http\Controllers\AccountController::class,'registerForm'])->name('account.register');
Route::post('/akun/daftar', [\App\Http\Controllers\AccountController::class,'register'])->middleware('throttle:3,1');
Route::middleware(\App\Http\Middleware\RequireAccount::class)->prefix('akun')->name('account.')->group(function(){
    Route::get('/',[\App\Http\Controllers\AccountController::class,'index'])->name('index');
    Route::post('/keluar',[\App\Http\Controllers\AccountController::class,'logout'])->name('logout');
    Route::post('/shortlist/{journal}',[\App\Http\Controllers\AccountController::class,'saveJournal'])->name('save');
    Route::delete('/shortlist/{shortlist}',[\App\Http\Controllers\AccountController::class,'removeJournal'])->name('remove');
    Route::delete('/riwayat/{history}',[\App\Http\Controllers\AccountController::class,'removeHistory'])->name('history.remove');
    Route::put('/profil',[\App\Http\Controllers\AccountController::class,'profile'])->name('profile');
    Route::put('/password',[\App\Http\Controllers\AccountController::class,'password'])->middleware('throttle:5,1')->name('password');
});

Route::get('/jurnal/{journal}/checklist', [\App\Http\Controllers\ChecklistController::class,'show'])->name('checklist.show');
Route::post('/akun/checklist/{journal}', [\App\Http\Controllers\ChecklistController::class,'save'])->middleware([\App\Http\Middleware\RequireAccount::class,'throttle:20,1'])->name('checklist.save');

Route::middleware(\App\Http\Middleware\RequireAccount::class)->prefix('akun/submission')->name('submissions.')->group(function () {
    Route::get('/', [\App\Http\Controllers\SubmissionController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\SubmissionController::class, 'store'])->middleware('throttle:20,1')->name('store');
    Route::get('/{submission}', [\App\Http\Controllers\SubmissionController::class, 'show'])->whereNumber('submission')->name('show');
    Route::put('/{submission}', [\App\Http\Controllers\SubmissionController::class, 'update'])->middleware('throttle:30,1')->name('update');
    Route::get('/{submission}/matriks.csv', [\App\Http\Controllers\SubmissionController::class, 'export'])->middleware('throttle:10,1')->name('export');
    Route::post('/{submission}/respons', [\App\Http\Controllers\SubmissionController::class, 'responseStore'])->middleware('throttle:30,1')->name('responses.store');
    Route::put('/{submission}/respons/{response}', [\App\Http\Controllers\SubmissionController::class, 'responseUpdate'])->middleware('throttle:30,1')->name('responses.update');
});

Route::get('/layanan', [\App\Http\Controllers\OperationsController::class,'status'])->name('service.status');
Route::middleware(\App\Http\Middleware\RequireAccount::class)->prefix('akun/pembayaran')->name('payments.')->group(function () {
    Route::get('/', [\App\Http\Controllers\PaymentController::class,'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\PaymentController::class,'store'])->middleware('throttle:5,1')->name('store');
    Route::post('/{payment}/batal', [\App\Http\Controllers\PaymentController::class,'cancel'])->middleware('throttle:10,1')->name('cancel');
});
Route::middleware(['auth',RequireAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/operasional', [\App\Http\Controllers\OperationsController::class,'index'])->middleware('throttle:10,1')->name('operations');
    Route::get('/pembayaran', [\App\Http\Controllers\PaymentController::class,'admin'])->name('payments');
    Route::post('/pembayaran/{payment}/verifikasi', [\App\Http\Controllers\PaymentController::class,'review'])->middleware('throttle:10,1')->name('payments.review');
});

Route::get('/sumber-jurnal', [\App\Http\Controllers\JournalSourceController::class,'index'])->name('sources.index');
Route::get('/sumber-jurnal/{source}', [\App\Http\Controllers\JournalSourceController::class,'show'])->name('sources.show');
Route::middleware(['auth',RequireAdmin::class])->prefix('admin/sumber-jurnal')->name('admin.sources.')->group(function(){
    Route::get('/{source}', [\App\Http\Controllers\JournalSourceController::class,'prepare'])->name('prepare');
    Route::post('/{source}/draft', [\App\Http\Controllers\JournalSourceController::class,'promote'])->middleware('throttle:10,1')->name('promote');
});
