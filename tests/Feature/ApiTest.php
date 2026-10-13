<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private array $unitIds;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create(['name' => 'Empresa Teste']);
        $this->unitIds = collect(['A', 'B'])
            ->map(fn ($n) => $company->units()->create(['name' => "Unidade $n"])->id)
            ->all();
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function brlExpense(string $date, string $amount, array $allocations): array
    {
        return [
            'description' => 'Despesa', 'supplier' => 'Fornecedor', 'expense_date' => $date,
            'currency' => 'BRL', 'amount' => $amount, 'allocations' => $allocations,
        ];
    }

    public function test_rota_protegida_sem_token_retorna_401(): void
    {
        $this->getJson('/api/expenses')->assertUnauthorized();
    }

    public function test_login_devolve_token(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'password' => bcrypt('password')]);

        $this->postJson('/api/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_cria_despesa_pela_api(): void
    {
        $this->authenticate();

        $this->postJson('/api/expenses', $this->brlExpense('2026-09-01', '100.00', [
            ['unit_id' => $this->unitIds[0], 'percentage' => '50'],
            ['unit_id' => $this->unitIds[1], 'percentage' => '50'],
        ]))
            ->assertCreated()
            ->assertJsonPath('status', 'converted')
            ->assertJsonPath('amount_brl_cents', 10000);
    }

    public function test_rateio_invalido_retorna_422(): void
    {
        $this->authenticate();

        $this->postJson('/api/expenses', $this->brlExpense('2026-09-01', '100.00', [
            ['unit_id' => $this->unitIds[0], 'percentage' => '70'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('allocations');
    }

    public function test_importa_csv_pela_api(): void
    {
        $this->authenticate();

        $file = UploadedFile::fake()->createWithContent('despesas.csv', implode("\n", [
            'data;descricao;fornecedor;valor;moeda;rateio',
            '2026-09-01;Internet;Fornecedor Y;100.00;BRL;unidade-a:100',
            '2026-09-02;Aluguel;Fornecedor Z;200.00;BRL;unidade-x:100',
        ]));

        $this->postJson('/api/expenses/import', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('errors.0.line', 3);
    }

    public function test_relatorio_soma_por_unidade_no_periodo(): void
    {
        $this->authenticate();
        [$a, $b] = $this->unitIds;

        $this->postJson('/api/expenses', $this->brlExpense('2026-09-01', '100.00', [
            ['unit_id' => $a, 'percentage' => '50'], ['unit_id' => $b, 'percentage' => '50'],
        ]));
        $this->postJson('/api/expenses', $this->brlExpense('2026-09-15', '60.00', [
            ['unit_id' => $a, 'percentage' => '100'],
        ]));
        $this->postJson('/api/expenses', $this->brlExpense('2026-10-01', '999.00', [ // fora do período
            ['unit_id' => $a, 'percentage' => '100'],
        ]));

        $this->getJson('/api/reports/units?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('units.0.slug', 'unidade-a')
            ->assertJsonPath('units.0.total_brl_cents', 11000)   // 50,00 + 60,00
            ->assertJsonPath('units.1.total_brl_cents', 5000)    // 50,00
            ->assertJsonPath('pending_expenses', 0);
    }

    public function test_excluir_despesa_inexistente_retorna_404_com_mensagem(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/expenses/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Registro não encontrado (id: 999).');
    }
}