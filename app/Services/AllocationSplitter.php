<?php

namespace App\Services;

use InvalidArgumentException;

class AllocationSplitter
{
    /**
     * Divide um valor em centavos conforme percentuais em pontos-base (10000 = 100%),
     * garantindo que a soma das partes seja exatamente igual ao total.
     *
     * Método do maior resto:
     * 1. cada parte recebe o valor arredondado para baixo;
     * 2. os centavos que sobram vão, um a um, para as partes com maior fração descartada.
     *
     * @param  array<int|string, int>  $percentages  chave => pontos-base
     * @return array<int|string, int>  chave => centavos
     */
    public function split(int $totalCents, array $percentages): array
    {
        if (array_sum($percentages) !== 10000) {
            throw new InvalidArgumentException('A soma do rateio precisa ser 100%.');
        }

        $amounts = [];
        $remainders = [];

        foreach ($percentages as $key => $basisPoints) {
            $exact = $totalCents * $basisPoints;           // tudo em inteiro, sem float
            $amounts[$key] = intdiv($exact, 10000);        // parte arredondada para baixo
            $remainders[$key] = $exact % 10000;            // fração que foi descartada
        }

        $leftover = $totalCents - array_sum($amounts);     // centavos que sobraram

        arsort($remainders);                               // maiores frações primeiro

        foreach (array_keys($remainders) as $key) {
            if ($leftover === 0) {
                break;
            }
            $amounts[$key]++;
            $leftover--;
        }

        return $amounts;
    }
}