<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateExpense;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Unit;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index()
    {
        return view('expenses.index', [
            'expenses' => Expense::with('allocations.unit')->latest('expense_date')->latest('id')->simplePaginate(15),
        ]);
    }

    public function create()
    {
        return view('expenses.create', [
            'units' => Unit::with('company')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CreateExpense $createExpense)
    {
        $createExpense->handle([
            ...$request->except('allocations'),
            'allocations' => $this->allocationsFromForm($request),
        ]);

        return redirect()->route('expenses.index')->with('success', 'Despesa cadastrada.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Despesa excluída.');
    }

    // O formulário envia allocations[unit_id] = percentual; campos vazios ficam fora do rateio
    private function allocationsFromForm(Request $request): array
    {
        return collect($request->input('allocations', []))
            ->reject(fn ($percentage) => $percentage === null || $percentage === '')
            ->map(fn ($percentage, $unitId) => ['unit_id' => $unitId, 'percentage' => $percentage])
            ->values()
            ->all();
    }
}