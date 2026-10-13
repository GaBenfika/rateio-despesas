<?php

namespace App\Services;

use RuntimeException;

use App\Actions\CreateExpense;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CsvExpenseImporter
{
    private const EXPECTED_COLUMNS = 6; // data;descricao;fornecedor;valor;moeda;rateio

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
        $unitsByCode = Unit::pluck('id', 'code'); // ['unidade-a' => 1, ...] carregado uma vez só
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
                $this->createExpense->handle($this->rowToExpenseData($row, $unitsByCode));
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

    private function rowToExpenseData(array $row, Collection $unitsByCode): array
    {
        if (count($row) !== self::EXPECTED_COLUMNS) {
            throw ValidationException::withMessages([
                'line' => 'Esperadas '.self::EXPECTED_COLUMNS.' colunas separadas por ";", recebidas '.count($row).'.',
            ]);
        }

        [$date, $description, $supplier, $amount, $currency, $allocations] = array_map('trim', $row);

        return [
            'expense_date' => $date,
            'description' => $description,
            'supplier' => $supplier,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'allocations' => $this->parseAllocations($allocations, $unitsByCode),
        ];
    }

    /**
     * "unidade-a:50|unidade-b:50" → [['unit_id' => 1, 'percentage' => '50'], ['unit_id' => 2, ...]]
     */
    private function parseAllocations(string $text, Collection $unitsByCode): array
    {
        $allocations = [];

        foreach (explode('|', $text) as $part) {
            [$code, $percentage] = array_pad(explode(':', $part, 2), 2, null);
            $code = trim($code);

            if ($percentage === null) {
                throw ValidationException::withMessages([
                    'allocations' => "Rateio inválido: \"{$part}\". Formato esperado: unidade:percentual.",
                ]);
            }

            if (! $unitsByCode->has($code)) {
                throw ValidationException::withMessages([
                    'allocations' => "Unidade \"{$code}\" não encontrada.",
                ]);
            }

            $allocations[] = ['unit_id' => $unitsByCode[$code], 'percentage' => trim($percentage)];
        }

        return $allocations;
    }
}