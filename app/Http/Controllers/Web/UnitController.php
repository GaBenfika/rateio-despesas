<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UnitController extends Controller
{
    public function index()
    {
        return view('units.index', [
            'units' => Unit::with('company')->orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        // Sem código informado, gera a partir do nome antes de validar, para checar também se já existe
        $request->merge(['code' => $request->input('code') ?: Str::slug((string) $request->input('name'))]);

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'alpha_dash', 'max:255', 'unique:units,code'],
        ], [
            'code.required' => 'Informe o código: não foi possível gerá-lo a partir do nome.',
            'code.unique' => 'Já existe uma unidade com o código ":input". Informe outro código.',
        ]);

        Unit::create($data);

        return redirect()->route('units.index')->with('success', 'Unidade cadastrada.');
    }
}