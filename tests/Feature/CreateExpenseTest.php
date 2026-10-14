<?php

namespace Tests\Feature;

use App\Actions\CreateExpense;
use App\Jobs\ConvertExpenseToBrl;
use App\Models\Company;
use App\Models\Expense;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Queue;

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

    public function test_despesa_em_usd_fica_pendente_e_vai_para_a_fila(): void
    {
        Queue::fake(); // segura o job na fila, sem executar

        $expense = app(CreateExpense::class)->handle($this->payload(['currency' => 'USD']));

        $this->assertSame(Expense::STATUS_PENDING, $expense->status);
        $this->assertNull($expense->amount_brl_cents);
        $this->assertCount(3, $expense->allocations);
        Queue::assertPushed(ConvertExpenseToBrl::class);
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

    public function test_despesa_em_usd_com_data_futura_agenda_a_conversao_para_o_dia_seguinte(): void
    {
        Queue::fake();
        $this->travelTo('2026-10-13 22:00:00'); // horário de Brasília; em UTC já seria dia 14

        $expense = app(CreateExpense::class)->handle($this->payload([
            'currency' => 'USD',
            'expense_date' => '2026-10-14', // amanhã
        ]));

        $this->assertSame(Expense::STATUS_PENDING, $expense->status);
        Queue::assertPushed(
            ConvertExpenseToBrl::class,
            fn (ConvertExpenseToBrl $job) => $job->delay->toDateTimeString() === '2026-10-15 00:00:00'
        );
    }

    public function test_despesa_em_usd_de_hoje_ou_passada_converte_sem_esperar(): void
    {
        Queue::fake();
        $this->travelTo('2026-10-13 22:00:00');

        app(CreateExpense::class)->handle($this->payload(['currency' => 'USD', 'expense_date' => '2026-10-13']));

        Queue::assertPushed(ConvertExpenseToBrl::class, fn (ConvertExpenseToBrl $job) => $job->delay === null);
    }

    public function test_despesa_em_brl_com_data_futura_e_rateada_na_hora(): void
    {
        $this->travelTo('2026-10-13 22:00:00');

        $expense = app(CreateExpense::class)->handle($this->payload(['expense_date' => '2026-12-01']));

        $this->assertSame(Expense::STATUS_CONVERTED, $expense->status);
        $this->assertSame(10000, $expense->amount_brl_cents);
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