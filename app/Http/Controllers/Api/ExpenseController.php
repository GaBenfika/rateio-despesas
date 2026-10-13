<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateExpense;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index()
    {
        return Expense::with('allocations.unit')->latest('expense_date')->paginate(20);
    }

    public function store(Request $request, CreateExpense $createExpense)
    {
        $expense = $createExpense->handle($request->all());

        return response()->json($expense->load('allocations.unit'), 201);
    }

    public function show(Expense $expense)
    {
        return $expense->load('allocations.unit');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete(); // as alocações saem junto (cascade)

        return response()->noContent();
    }
}