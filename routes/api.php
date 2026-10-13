<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ExpenseImportController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UnitReportController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/units', [UnitController::class, 'index']);
    Route::post('/units', [UnitController::class, 'store']);

    Route::post('/expenses/import', ExpenseImportController::class);
    Route::apiResource('expenses', ExpenseController::class)->except('update');

    Route::get('/reports/units', UnitReportController::class);
});