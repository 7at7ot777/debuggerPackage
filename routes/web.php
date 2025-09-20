<?php

use Illuminate\Support\Facades\Route;
use MohamedHathout\Debugger\Http\Controllers\DebuggerController;
use MohamedHathout\Debugger\Livewire\DebugViewer;
use MohamedHathout\Debugger\Http\Middleware\DebuggerEnabled;

Route::middleware(['web', DebuggerEnabled::class])->group(function () {
    // Web route for the Livewire component
    Route::get(config('debugger.route_name', 'debugger'), DebugViewer::class)
        ->name('debugger_index');

    // API routes for AJAX operations
    Route::prefix('api/debugger')->name('debugger.')->group(function () {
        Route::get('data', [DebuggerController::class, 'getDebugData'])->name('data');
        Route::get('files', [DebuggerController::class, 'getFiles'])->name('files');
        Route::post('clear', [DebuggerController::class, 'clearAll'])->name('clear');
    });
});
