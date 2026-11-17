<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SpreadsheetController;
use App\Http\Controllers\SpreadsheetTabController;
use App\Http\Controllers\SyncRunController;
use App\Http\Controllers\TabMappingController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('sync', [DashboardController::class, 'syncAll'])->name('sync.all');

    Route::resource('spreadsheets', SpreadsheetController::class)->except('index');
    Route::post('spreadsheets/{spreadsheet}/sync', [SpreadsheetController::class, 'sync'])->name('spreadsheets.sync');
    Route::get('spreadsheets/{spreadsheet}/tab-names', [SpreadsheetController::class, 'tabNames'])->name('spreadsheets.tab-names');

    Route::post('spreadsheets/{spreadsheet}/tabs', [SpreadsheetTabController::class, 'store'])->name('tabs.store');
    Route::get('tabs/{tab}', [SpreadsheetTabController::class, 'show'])->name('tabs.show');
    Route::put('tabs/{tab}', [SpreadsheetTabController::class, 'update'])->name('tabs.update');
    Route::delete('tabs/{tab}', [SpreadsheetTabController::class, 'destroy'])->name('tabs.destroy');
    Route::get('tabs/{tab}/headers', [SpreadsheetTabController::class, 'headers'])->name('tabs.headers');

    Route::get('tabs/{tab}/mappings/create', [TabMappingController::class, 'create'])->name('mappings.create');
    Route::post('tabs/{tab}/mappings', [TabMappingController::class, 'store'])->name('mappings.store');
    Route::get('mappings/{mapping}/edit', [TabMappingController::class, 'edit'])->name('mappings.edit');
    Route::put('mappings/{mapping}', [TabMappingController::class, 'update'])->name('mappings.update');
    Route::delete('mappings/{mapping}', [TabMappingController::class, 'destroy'])->name('mappings.destroy');

    Route::get('runs', [SyncRunController::class, 'index'])->name('runs.index');
    Route::get('runs/{run}', [SyncRunController::class, 'show'])->name('runs.show');

    Route::get('configuration', [ConfigurationController::class, 'index'])->name('configuration.index');
    Route::get('configuration/export', [ConfigurationController::class, 'export'])->name('configuration.export');
    Route::post('configuration/import', [ConfigurationController::class, 'import'])->name('configuration.import');
});
