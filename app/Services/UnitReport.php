<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class UnitReport
{
    /**
     * Total em BRL por unidade no período (pela data da despesa).
     * Despesas ainda não convertidas não entram na soma, mas são contadas em "pending_expenses".
     */
    public function between(string $from, string $to): array
    {
        $units = DB::table('expense_allocations as a')
            ->join('expenses as e', 'e.id', '=', 'a.expense_id')
            ->join('units as u', 'u.id', '=', 'a.unit_id')
            ->whereBetween('e.expense_date', [$from, $to])
            ->groupBy('u.id', 'u.name', 'u.slug')
            ->orderBy('u.name')
            ->select('u.id', 'u.name', 'u.slug')
            ->selectRaw('COALESCE(SUM(a.amount_brl_cents), 0) as total_brl_cents')
            ->get()
            ->map(fn ($row) => [
                'unit_id' => $row->id,
                'unit' => $row->name,
                'slug' => $row->slug,
                'total_brl_cents' => (int) $row->total_brl_cents,
                'total_brl' => number_format($row->total_brl_cents / 100, 2, '.', ''),
            ]);

        $pending = Expense::whereBetween('expense_date', [$from, $to])
            ->where('status', Expense::STATUS_PENDING)
            ->count();

        return [
            'from' => $from,
            'to' => $to,
            'pending_expenses' => $pending, // > 0 significa que o total ainda pode mudar
            'units' => $units,
        ];
    }
}