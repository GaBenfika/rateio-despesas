<?php

namespace App\Services;

use InvalidArgumentException;

class AllocationSplitter
{
    public const ONE_HUNDRED_PERCENT = 10000; // em pontos-base

    /**
     * Divide um valor em centavos conforme percentuais em pontos-base (10000 = 100%),
     * garantindo que a soma das partes seja exatamente igual ao total.
     *
     * Método do maior resto:
     * 1. cada parte recebe o valor arredondado para baixo;
     * 2. os centavos que sobram vão, um a um, para as partes com maior fração descartada.
     *
     * @param  array<int|string, int>  $basisPointsByKey  chave => pontos-base
     * @return array<int|string, int>  chave => centavos
     */
    public function split(int $totalCents, array $basisPointsByKey): array
    {
        if (array_sum($basisPointsByKey) !== self::ONE_HUNDRED_PERCENT) {
            throw new InvalidArgumentException('A soma do rateio precisa ser 100%.');
        }

        $centsByKey = [];
        $discardedFractions = [];

        foreach ($basisPointsByKey as $key => $basisPoints) {
            $exactShare = $totalCents * $basisPoints;                               // tudo em inteiro, sem float
            $centsByKey[$key] = intdiv($exactShare, self::ONE_HUNDRED_PERCENT);     // parte arredondada para baixo
            $discardedFractions[$key] = $exactShare % self::ONE_HUNDRED_PERCENT;    // fração que foi descartada
        }

        $missingCents = $totalCents - array_sum($centsByKey);                       // centavos que sobraram

        arsort($discardedFractions);                                                // maiores frações primeiro

        foreach (array_keys($discardedFractions) as $key) {
            if ($missingCents === 0) {
                break;
            }
            $centsByKey[$key]++;
            $missingCents--;
        }

        return $centsByKey;
    }
}
