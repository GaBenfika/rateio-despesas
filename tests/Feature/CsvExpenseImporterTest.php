<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Services\CsvExpenseImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CsvExpenseImporterTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'data;descricao;fornecedor;valor;moeda;rateio';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake(); // a conversão USD não é o foco aqui

        $company = Company::create(['name' => 'Empresa Teste']);
        foreach (['Unidade A', 'Unidade B', 'Unidade C'] as $name) {
            $company->units()->create(['name' => $name]); // slugs: unidade-a, unidade-b, unidade-c
        }
    }

    private function csvFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);

        return $path;
    }

    public function test_importa_as_linhas_validas_e_relata_as_invalidas(): void
    {
        $path = $this->csvFile(implode("\n", [
            self::HEADER,
            '2026-09-01;Licença CRM;Fornecedor X;1500.00;USD;unidade-a:50|unidade-b:30|unidade-c:20', // 2: ok
            '2026-09-02;Internet;Fornecedor Y;100.00;BRL;unidade-a:50|unidade-b:40',                 // 3: soma 90%
            '2026-09-03;Aluguel;Fornecedor Z;2000.00;BRL;unidade-x:100',                              // 4: unidade inexistente
            '2026-09-04;Linha quebrada;Fornecedor W',                                                 // 5: colunas faltando
            '2026-09-05;Café;Padaria;80.00;BRL;unidade-a:100',                                        // 6: ok
        ]));

        $result = app(CsvExpenseImporter::class)->import($path);

        $this->assertSame(2, $result['imported']);
        $this->assertSame([3, 4, 5], array_column($result['errors'], 'line'));
        $this->assertSame(2, Expense::count());
    }

    public function test_aceita_arquivo_salvo_pelo_excel_com_bom(): void
    {
        $path = $this->csvFile("\xEF\xBB\xBF".self::HEADER."\n2026-09-05;Café;Padaria;80.00;BRL;unidade-a:100\n");

        $result = app(CsvExpenseImporter::class)->import($path);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);
    }

    public function test_ignora_linhas_em_branco(): void
    {
        $path = $this->csvFile(self::HEADER."\n\n2026-09-05;Café;Padaria;80.00;BRL;unidade-a:100\n\n");

        $result = app(CsvExpenseImporter::class)->import($path);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);
    }
}