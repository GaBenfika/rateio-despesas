<?php

namespace App\Jobs;

use App\Actions\ApplyExchangeRate;
use App\Models\Expense;
use App\Services\ExchangeRateClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConvertExpenseCurrency implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;                      // até 5 tentativas
    public array $backoff = [60, 300, 900];     // espera 1 min, 5 min, 15 min (e 15 nas seguintes)

    public function __construct(public Expense $expense)
    {
    }

    public function handle(ExchangeRateClient $client, ApplyExchangeRate $applyExchangeRate): void
    {
        // Idempotência: se já foi convertida (ex.: job duplicado), não faz nada
        if (! $this->expense->isPending()) {
            return;
        }

        $quote = $client->usdToBrl($this->expense->expense_date->toDateString());

        $applyExchangeRate->handle($this->expense, $quote['rate'], $quote['date']);
    }

    public function failed(Throwable $exception): void
    {
        // Esgotou as tentativas: a despesa CONTINUA salva como pending.
        // O job fica em failed_jobs e pode ser reprocessado com: sail artisan queue:retry all
        Log::warning("Falha ao converter a despesa {$this->expense->id}: {$exception->getMessage()}");
    }
}