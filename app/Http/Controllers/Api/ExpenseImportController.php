<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CsvExpenseImporter;
use Illuminate\Http\Request;

class ExpenseImportController extends Controller
{
    public function __invoke(Request $request, CsvExpenseImporter $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'], // até 2 MB
        ]);

        return $importer->import($request->file('file')->getRealPath());
    }
}