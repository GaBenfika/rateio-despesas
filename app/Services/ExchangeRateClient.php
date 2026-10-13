<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExchangeRateClient
{
    /**
     * Busca a cotação USD → BRL de uma data.
     * Em fim de semana ou feriado, a Frankfurter devolve a do último dia útil,
     * e o campo "date" informa qual dia foi usado.
     *
     * @return array{rate: string, rate_date: string}
     */
    public function usdToBrl(string $date): array
    {
        $response = Http::timeout(10)
            ->get(config('services.exchange_rate.url')."/{$date}", [
                'base' => 'USD',
                'symbols' => 'BRL',
            ])
            ->throw(); // erro 4xx/5xx vira exceção → o job falha e tenta de novo

        $rate = $response->json('rates.BRL');

        if ($rate === null) {
            throw new RuntimeException("Cotação USD/BRL não encontrada para {$date}.");
        }

        return [
            'rate' => (string) $rate,
            'rate_date' => $response->json('date'),
        ];
    }
}