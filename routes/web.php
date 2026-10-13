<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ExpenseController;
use App\Http\Controllers\Web\ExpenseImportController;
use App\Http\Controllers\Web\UnitController;
use App\Http\Controllers\Web\UnitReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::redirect('/', '/expenses');

    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    Route::get('/expenses/import', [ExpenseImportController::class, 'create'])->name('expenses.import');
    Route::post('/expenses/import', [ExpenseImportController::class, 'store'])->name('expenses.import.store');

    Route::get('/reports/units', [UnitReportController::class, 'index'])->name('reports.units');
});
