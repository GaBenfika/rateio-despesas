<?php

namespace App\Actions;

use App\Jobs\ConvertExpenseCurrency;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateExpense
{
    public function __construct(private ApplyExchangeRate $applyExchangeRate)
    {
    }

    /**
     * Ponto único de criação de despesa: usado pela API, pela tela e pela importação CSV.
     *
     * @throws ValidationException
     */
    public function handle(array $input): Expense
    {
        $data = $this->validate($input);

        return DB::transaction(function () use ($data) {
            $expense = Expense::create([
                'description' => $data['description'],
                'supplier' => $data['supplier'],
                'expense_date' => $data['expense_date'],
                'currency' => $data['currency'],
                'amount_cents' => $this->toCents($data['amount']),
                'status' => Expense::STATUS_PENDING,
            ]);

            foreach ($data['allocations'] as $allocation) {
                $expense->allocations()->create([
                    'unit_id' => $allocation['unit_id'],
                    'percentage_bp' => $this->toBasisPoints($allocation['percentage']),
                ]);
            }

            if ($expense->currency === 'BRL') {
                // BRL não precisa de câmbio: cotação 1 e rateio imediato
                $this->applyExchangeRate->handle($expense, '1', $data['expense_date']);
            } else {
                // USD: converte na fila, só depois que a transação for confirmada
                ConvertExpenseCurrency::dispatch($expense)->afterCommit();
            }
            // USD: fica pendente; a conversão via fila entra no próximo passo

            return $expense->fresh('allocations');
        });
    }

    private function validate(array $input): array
    {
        $data = Validator::make($input, [
            'description' => ['required', 'string', 'max:255'],
            'supplier' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'currency' => ['required', 'in:BRL,USD'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.unit_id' => ['required', 'integer', 'distinct', 'exists:units,id'],
            'allocations.*.percentage' => ['required', 'decimal:0,2', 'gt:0', 'max:100'],
        ])->validate();

        $total = array_sum(array_map(
            fn ($allocation) => $this->toBasisPoints($allocation['percentage']),
            $data['allocations']
        ));

        if ($total !== 10000) {
            throw ValidationException::withMessages([
                'allocations' => 'A soma do rateio precisa ser exatamente 100% (recebido: '.($total / 100).'%).',
            ]);
        }

        return $data;
    }

    // "1500.00" → 150000
    private function toCents(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    // "33.33" → 3333
    private function toBasisPoints(string|int|float $percentage): int
    {
        return (int) round((float) $percentage * 100);
    }
}