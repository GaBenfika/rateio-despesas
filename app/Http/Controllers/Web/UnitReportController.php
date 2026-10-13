<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\UnitReport;
use Illuminate\Http\Request;

class UnitReportController extends Controller
{
    public function index(Request $request, UnitReport $report)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->endOfMonth()->toDateString();

        return view('reports.units', ['report' => $report->between($from, $to)]);
    }
}