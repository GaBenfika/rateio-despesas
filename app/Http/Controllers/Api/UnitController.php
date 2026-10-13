<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        return Unit::with('company')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255', 'unique:units,slug'],
        ]);

        return response()->json(Unit::create($data)->load('company'), 201);
    }
}