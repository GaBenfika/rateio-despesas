<?php

namespace Tests\Feature;

use App\Actions\CreateExpense;
use App\Models\Company;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateExpenseTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Licença CRM',
            'supplier' => 'Fornecedor X',
            'expense_date' => '2026-09-01',
            'currency' => 'BRL',
            'amount' => '100.00',
            'allocations' => [
                ['unit_id' => $this->unitIds[0], 'percentage' => '33.33'],
                ['unit_id' => $this->unitIds[1], 'percentage' => '33.33'],
                ['unit_id' => $this->unitIds[2], 'percentage' => '33.34'],
            ],
        ], $overrides);
    }

    public function test_despesa_em_brl_e_rateada_e_os_centavos_fecham(): void
    {
        $expense = app(CreateExpense::class)->handle($this->payload());

        $this->assertSame(Expense::STATUS_CONVERTED, $expense->status);
        $this->assertSame(10000, $expense->amount_brl_cents);
        $this->assertSame(10000, (int) $expense->allocations->sum('amount_brl_cents'));
    }

    public function test_despesa_em_usd_fica_pendente_ate_a_conversao(): void
    {
        $expense = app(CreateExpense::class)->handle($this->payload(['currency' => 'USD']));

        $this->assertSame(Expense::STATUS_PENDING, $expense->status);
        $this->assertNull($expense->amount_brl_cents);
        $this->assertCount(3, $expense->allocations); // o rateio em % já está salvo
    }

    public function test_rejeita_rateio_que_nao_soma_cem_por_cento(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(CreateExpense::class)->handle($this->payload([
                'allocations' => [
                    ['unit_id' => $this->unitIds[0], 'percentage' => '50'],
                    ['unit_id' => $this->unitIds[1], 'percentage' => '40'],
                ],
            ]));
        } finally {
            $this->assertSame(0, Expense::count()); // nada foi gravado
        }
    }

    public function test_rejeita_a_mesma_unidade_repetida_no_rateio(): void
    {
        $this->expectException(ValidationException::class);

        app(CreateExpense::class)->handle($this->payload([
            'allocations' => [
                ['unit_id' => $this->unitIds[0], 'percentage' => '50'],
                ['unit_id' => $this->unitIds[0], 'percentage' => '50'],
            ],
        ]));
    }
}