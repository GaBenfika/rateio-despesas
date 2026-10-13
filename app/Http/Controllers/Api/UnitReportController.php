<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UnitReport;
use Illuminate\Http\Request;

class UnitReportController extends Controller
{
    public function __invoke(Request $request, UnitReport $report)
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return $report->between($data['from'], $data['to']);
    }
}