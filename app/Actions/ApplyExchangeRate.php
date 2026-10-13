<?php

namespace App\Actions;

use App\Models\Expense;
use App\Services\AllocationSplitter;
use Illuminate\Support\Facades\DB;

class ApplyExchangeRate
{
    public function __construct(private AllocationSplitter $splitter)
    {
    }

    /**
     * Converte a despesa para BRL com a cotação informada e rateia o valor entre as unidades.
     */
    public function handle(Expense $expense, string $rate, string $rateDate): void
    {
        $amountBrlCents = (int) round($expense->amount_cents * (float) $rate);

        DB::transaction(function () use ($expense, $rate, $rateDate, $amountBrlCents) {
            $allocations = $expense->allocations;

            $amounts = $this->splitter->split(
                $amountBrlCents,
                $allocations->pluck('percentage_bp', 'id')->all() // [allocation_id => pontos-base]
            );

            foreach ($allocations as $allocation) {
                $allocation->update(['amount_brl_cents' => $amounts[$allocation->id]]);
            }

            $expense->update([
                'exchange_rate' => $rate,
                'exchange_rate_date' => $rateDate,
                'amount_brl_cents' => $amountBrlCents,
                'status' => Expense::STATUS_CONVERTED,
            ]);
        });
    }
}