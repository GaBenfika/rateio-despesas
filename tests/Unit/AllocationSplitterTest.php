<?php

namespace Tests\Unit;

use App\Services\AllocationSplitter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AllocationSplitterTest extends TestCase
{
    private AllocationSplitter $splitter;

    protected function setUp(): void
    {
        $this->splitter = new AllocationSplitter();
    }

    public function test_cem_reais_em_tres_partes_fecha_o_total(): void
    {
        // R$ 100,00 em 33,33% / 33,33% / 33,34%
        $result = $this->splitter->split(10000, ['a' => 3333, 'b' => 3333, 'c' => 3334]);

        $this->assertSame(['a' => 3333, 'b' => 3333, 'c' => 3334], $result);
        $this->assertSame(10000, array_sum($result));
    }

    public function test_centavo_que_sobra_vai_para_a_maior_fracao(): void
    {
        // R$ 10,00: as partes exatas seriam 3,333 / 3,333 / 3,334 → sobra 1 centavo
        $result = $this->splitter->split(1000, ['a' => 3333, 'b' => 3333, 'c' => 3334]);

        $this->assertSame(['a' => 333, 'b' => 333, 'c' => 334], $result);
    }

    public function test_um_centavo_dividido_ao_meio(): void
    {
        // Empate: o centavo vai para a primeira unidade, de forma previsível
        $result = $this->splitter->split(1, ['a' => 5000, 'b' => 5000]);

        $this->assertSame(1, array_sum($result));
    }

    public function test_exemplo_em_dolar_com_rateio_50_30_20(): void
    {
        // US$ 1.500,00 convertido para, por exemplo, R$ 8.123,47, em 50/30/20
        $result = $this->splitter->split(812347, ['a' => 5000, 'b' => 3000, 'c' => 2000]);

        $this->assertSame(812347, array_sum($result));
    }

    public function test_rejeita_rateio_que_nao_soma_cem_por_cento(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->splitter->split(10000, ['a' => 5000, 'b' => 4000]);
    }
}