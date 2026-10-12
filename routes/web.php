<?php

use App\Http\Controllers\CatalogController as C;
use App\Http\Middleware\RequireAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/cocokkan', [\App\Http\Controllers\MatchController::class, 'form'])->name('match.form');
Route::post('/cocokkan', [\App\Http\Controllers\MatchController::class, 'match'])->middleware('throttle:20,1')->name('match.run');
Route::get('/', [C::class, 'index'])->name('catalog.index');
Route::get('/bandingkan', [C::class, 'compare'])->name('catalog.compare');
Route::get('/jurnal/{journal}', [C::class, 'show'])->name('catalog.show');
Route::get('/admin/login', [C::class, 'loginForm'])->name('login');
Route::post('/admin/login', [C::class, 'login'])->middleware('throttle:10,1');
Route::middleware(['auth', RequireAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [C::class, 'admin'])->name('index');
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
