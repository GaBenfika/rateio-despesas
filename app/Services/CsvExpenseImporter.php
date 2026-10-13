<?php

namespace App\Services;

use RuntimeException;

use App\Actions\CreateExpense;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CsvExpenseImporter
{
    private const COLUMNS = 6; // data;descricao;fornecedor;valor;moeda;rateio

    public function __construct(private CreateExpense $createExpense)
    {
    }

    /**
     * Importa o CSV linha a linha. Uma linha inválida não impede as demais.
     *
     * @return array{imported: int, errors: list<array{line: int, messages: list<string>}>}
     */
    public function import(string $path): array
    {
        $unitsBySlug = Unit::pluck('id', 'slug'); // ['unidade-a' => 1, ...] carregado uma vez só
        $imported = 0;
        $errors = [];
        $lineNumber = 0;

        if (! is_readable($path)) {
            throw new RuntimeException("Não foi possível ler o arquivo: {$path}");
        }

        $handle = fopen($path, 'r');

        while (($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
            $lineNumber++;

            if ($row === [null]) {
                continue; // linha em branco
            }

            if ($lineNumber === 1) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]); // remove o BOM que o Excel adiciona
                if (strtolower(trim($row[0])) === 'data') {
                    continue; // cabeçalho
                }
            }

            try {
                $this->createExpense->handle($this->toPayload($row, $unitsBySlug));
                $imported++;
            } catch (ValidationException $e) {
                $errors[] = [
                    'line' => $lineNumber,
                    'messages' => collect($e->errors())->flatten()->all(),
                ];
            }
        }

        fclose($handle);

        return ['imported' => $imported, 'errors' => $errors];
    }

    private function toPayload(array $row, Collection $unitsBySlug): array
    {
        if (count($row) !== self::COLUMNS) {
            throw ValidationException::withMessages([
                'line' => 'Esperadas '.self::COLUMNS.' colunas separadas por ";", recebidas '.count($row).'.',
            ]);
        }

        [$date, $description, $supplier, $amount, $currency, $allocations] = array_map('trim', $row);

        return [
            'expense_date' => $date,
            'description' => $description,
            'supplier' => $supplier,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'allocations' => $this->parseAllocations($allocations, $unitsBySlug),
        ];
    }

    /**
     * "unidade-a:50|unidade-b:50" → [['unit_id' => 1, 'percentage' => '50'], ['unit_id' => 2, ...]]
     */
    private function parseAllocations(string $text, Collection $unitsBySlug): array
    {
        $allocations = [];

        foreach (explode('|', $text) as $part) {
            [$slug, $percentage] = array_pad(explode(':', $part, 2), 2, null);
            $slug = trim($slug);

            if ($percentage === null) {
                throw ValidationException::withMessages([
                    'allocations' => "Rateio inválido: \"{$part}\". Formato esperado: unidade:percentual.",
                ]);
            }

            if (! $unitsBySlug->has($slug)) {
                throw ValidationException::withMessages([
                    'allocations' => "Unidade \"{$slug}\" não encontrada.",
                ]);
            }

            $allocations[] = ['unit_id' => $unitsBySlug[$slug], 'percentage' => trim($percentage)];
        }

        return $allocations;
    }
}