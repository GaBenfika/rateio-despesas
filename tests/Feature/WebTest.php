<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/expenses')->assertRedirect('/login');
    }

    public function test_paginas_principais_carregam(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/expenses', '/expenses/create', '/expenses/import', '/units', '/reports/units'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_cria_despesa_pelo_formulario_ignorando_unidades_em_branco(): void
    {
        $company = Company::create(['name' => 'Empresa Teste']);
        $a = $company->units()->create(['name' => 'Unidade A']);
        $b = $company->units()->create(['name' => 'Unidade B']);
        $c = $company->units()->create(['name' => 'Unidade C']);

        $this->actingAs(User::factory()->create())
            ->post('/expenses', [
                'description' => 'Internet', 'supplier' => 'Fornecedor Y', 'expense_date' => '2026-09-01',
                'currency' => 'BRL', 'amount' => '100.00',
                'allocations' => [$a->id => '50', $b->id => '50', $c->id => ''], // C fica fora
            ])
            ->assertRedirect('/expenses');

        $this->assertSame(2, Expense::first()->allocations()->count());
    }

    public function test_importa_o_csv_de_exemplo_pela_tela_e_mostra_os_erros_por_linha(): void
    {
        Queue::fake(); // a linha em USD vai para a fila sem chamar a API de câmbio

        $company = Company::create(['name' => 'Empresa Teste']);
        foreach (['Unidade A', 'Unidade B', 'Unidade C'] as $name) {
            $company->units()->create(['name' => $name]);
        }

        $file = UploadedFile::fake()->createWithContent(
            'exemplo-importacao.csv',
            file_get_contents(base_path('docs/exemplo-importacao.csv'))
        );

        $this->actingAs(User::factory()->create())
            ->post('/expenses/import', ['file' => $file])
            ->assertOk()
            ->assertSeeInOrder(['3', 'linha(s) importada(s)', '3', 'com erro'])
            ->assertSee('A soma do rateio precisa ser exatamente 100% (recebido: 90%).')
            ->assertSee('Unidade com código &quot;unidade-x&quot; não encontrada.', false)
            ->assertSee('Esperadas 6 colunas', false);

        $this->assertSame(3, Expense::count());
    }

    public function test_importa_csv_salvo_pelo_excel_em_windows_1252(): void
    {
        $company = Company::create(['name' => 'Empresa Teste']);
        $company->units()->create(['name' => 'Unidade A']);

        $content = mb_convert_encoding(
            "data;descricao;fornecedor;valor;moeda;rateio\n2026-09-01;Licença;Café da Esquina;10.00;BRL;unidade-a:100\n",
            'Windows-1252',
            'UTF-8'
        );

        $this->actingAs(User::factory()->create())
            ->post('/expenses/import', ['file' => UploadedFile::fake()->createWithContent('excel.csv', $content)])
            ->assertOk();

        $this->assertSame('Licença', Expense::first()->description);
        $this->assertSame('Café da Esquina', Expense::first()->supplier);
    }

    public function test_recusa_arquivo_que_nao_e_csv(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/expenses/import', ['file' => UploadedFile::fake()->create('foto.png', 10, 'image/png')])
            ->assertSessionHasErrors(['file' => 'O campo arquivo precisa ser um arquivo do tipo: csv, txt.']);

        $this->assertSame(0, Expense::count());
    }

    public function test_recusa_despesa_com_texto_longo_ou_valor_acima_do_limite(): void
    {
        $unit = Company::create(['name' => 'Empresa Teste'])->units()->create(['name' => 'Unidade A']);
        $payload = [
            'description' => 'Internet', 'supplier' => 'Fornecedor Y', 'expense_date' => '2026-09-01',
            'currency' => 'BRL', 'amount' => '100.00', 'allocations' => [$unit->id => '100'],
        ];

        $this->actingAs(User::factory()->create());

        $this->post('/expenses', [...$payload, 'description' => str_repeat('a', 256)])
            ->assertSessionHasErrors(['description' => 'O campo descrição não pode ter mais de 255 caracteres.']);
        $this->post('/expenses', [...$payload, 'supplier' => str_repeat('a', 256)])->assertSessionHasErrors('supplier');
        $this->post('/expenses', [...$payload, 'amount' => '1000000000.00'])
            ->assertSessionHasErrors(['amount' => 'O valor máximo por despesa é 999.999.999,99.']);
        $this->post('/expenses', [...$payload, 'amount' => '10.555'])
            ->assertSessionHasErrors(['amount' => 'O campo valor precisa ser um número com até 2 casas decimais.']);

        $this->assertSame(0, Expense::count());
    }

    public function test_recusa_unidade_cujo_codigo_gerado_ja_existe_ou_fica_vazio(): void
    {
        $company = Company::create(['name' => 'Empresa Teste']);
        $company->units()->create(['name' => 'Unidade A']); // código: unidade-a

        $this->actingAs(User::factory()->create());

        $this->post('/units', ['company_id' => $company->id, 'name' => 'Unidade A'])
            ->assertSessionHasErrors(['code' => 'Já existe uma unidade com o código "unidade-a". Informe outro código.']);

        $this->post('/units', ['company_id' => $company->id, 'name' => '!!!'])
            ->assertSessionHasErrors(['code' => 'Informe o código: não foi possível gerá-lo a partir do nome.']);

        $this->post('/units', ['company_id' => $company->id, 'name' => str_repeat('@', 100)]) // "@" vira "-at-" no código
            ->assertSessionHasErrors('code');

        $this->post('/units', ['company_id' => $company->id, 'name' => 'Unidade A', 'code' => 'unidade-a-2'])
            ->assertRedirect('/units');

        $this->assertSame(2, $company->units()->count());
    }
}
