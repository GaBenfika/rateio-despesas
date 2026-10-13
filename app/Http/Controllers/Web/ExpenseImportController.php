<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CsvExpenseImporter;
use Illuminate\Http\Request;

class ExpenseImportController extends Controller
{
    public function create()
    {
        return view('expenses.import');
    }

    public function store(Request $request, CsvExpenseImporter $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        return view('expenses.import', [
            'result' => $importer->import($request->file('file')->getRealPath()),
        ]);
    }
}