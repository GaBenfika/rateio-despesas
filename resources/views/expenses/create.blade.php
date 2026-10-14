@extends('layouts.app')

@section('content')
    <div class="page-header">
        <h2>Nova despesa</h2>
        <a href="{{ route('expenses.index') }}" role="button" class="secondary outline">Voltar</a>
    </div>

    <form method="POST" action="{{ route('expenses.store') }}">
        @csrf
        <div class="card">
            <div class="grid">
                <label>Descrição <input type="text" name="description" value="{{ old('description') }}" maxlength="255" required></label>
                <label>Fornecedor <input type="text" name="supplier" value="{{ old('supplier') }}" maxlength="255" required></label>
            </div>
            <div class="grid">
                <label>Data <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
                <label>Valor <input type="number" name="amount" step="0.01" min="0.01" max="999999999.99" value="{{ old('amount') }}" required></label>
                <label>Moeda
                    <select name="currency">
                        <option value="BRL" @selected(old('currency') === 'BRL')>BRL</option>
                        <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="card">
            <h4>Rateio entre unidades</h4>
            <p><small>Deixe em branco as unidades que não participam. A soma precisa ser exatamente 100%.</small></p>
            <div class="grid">
                @foreach ($units as $unit)
                    <label>
                        {{ $unit->name }} <small>({{ $unit->company->name }})</small>
                        <input type="number" name="allocations[{{ $unit->id }}]" step="0.01" min="0" max="100"
                               placeholder="%" value="{{ old('allocations.'.$unit->id) }}" data-percentage>
                    </label>
                @endforeach
            </div>
            <p>Soma: <strong id="percentage-sum">0.00</strong>%</p>
        </div>

        <button type="submit">Salvar despesa</button>
    </form>

    <script>
        // Só um auxílio visual; a validação de verdade (soma = 100%) é feita no servidor
        const inputs = document.querySelectorAll('[data-percentage]');
        const sumEl = document.getElementById('percentage-sum');
        const updateSum = () => {
            let sum = 0;
            inputs.forEach(input => sum += parseFloat(input.value || 0));
            sumEl.textContent = sum.toFixed(2);
            sumEl.style.color = Math.abs(sum - 100) < 0.001 ? '#16a34a' : '#dc2626';
        };
        inputs.forEach(input => input.addEventListener('input', updateSum));
        updateSum();
    </script>
@endsection