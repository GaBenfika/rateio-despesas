<?php

namespace Tests\Feature;

use App\Actions\CreateExpense;
use App\Jobs\ConvertExpenseToBrl;
use App\Models\Company;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConvertExpenseToBrlTest extends TestCase
{
    use RefreshDatabase;

    private array $unitIds;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create(['name' => 'Empresa Teste']);
        $this->unitIds = collect(['A', 'B', 'C'])
            ->map(fn ($n) => $company->units()->create(['name' => "Unidade $n"])->id)
            ->all();
    }

    private function usdPayload(string $date = '2026-09-01'): array
    {
        return [
            'description' => 'Licença CRM',
            'supplier' => 'Fornecedor X',
            'expense_date' => $date,
            'currency' => 'USD',
            'amount' => '1500.00',
            'allocations' => [
                ['unit_id' => $this->unitIds[0], 'percentage' => '50'],
                ['unit_id' => $this->unitIds[1], 'percentage' => '30'],
                ['unit_id' => $this->unitIds[2], 'percentage' => '20'],
            ],
        ];
    }

    private function fakeRate(string $date, float $rate): void
    {
        Http::fake([
            '*' => Http::response(['amount' => 1, 'base' => 'USD', 'date' => $date, 'rates' => ['BRL' => $rate]]),
        ]);
    }

    public function test_converte_usd_para_brl_e_rateia(): void
    {
        $this->fakeRate('2026-09-01', 5.4321);

        $expense = app(CreateExpense::class)->handle($this->usdPayload())->fresh('allocations');

        $this->assertSame(Expense::STATUS_CONVERTED, $expense->status);
        $this->assertSame(814815, $expense->amount_brl_cents); // 1500,00 × 5,4321
        $this->assertSame(814815, (int) $expense->allocations->sum('amount_brl_cents'));
    }

    public function test_fim_de_semana_usa_cotacao_do_ultimo_dia_util(): void
    {
        $this->fakeRate('2026-09-04', 5.40); // sábado 05/09 → API responde com sexta 04/09

        $expense = app(CreateExpense::class)->handle($this->usdPayload('2026-09-05'))->fresh();

        $this->assertSame('2026-09-04', $expense->exchange_rate_date->toDateString());
    }

    public function test_api_fora_do_ar_nao_perde_a_despesa(): void
    {
        Queue::fake();
        $expense = app(CreateExpense::class)->handle($this->usdPayload());

        Http::fake(['*' => Http::response(null, 500)]);

        $this->expectException(RequestException::class); // o job falha → a fila tentará de novo

        try {
            app()->call([new ConvertExpenseToBrl($expense), 'handle']);
        } finally {
            $this->assertSame(1, Expense::count());
            $this->assertSame(Expense::STATUS_PENDING, $expense->fresh()->status);
        }
    }
}