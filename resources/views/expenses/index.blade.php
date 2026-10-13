@extends('layouts.app')

@section('content')
    <div class="page-header">
        <h2>Despesas</h2>
        <a href="{{ route('expenses.create') }}" role="button">➕ Nova despesa</a>
    </div>

    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Data</th><th>Descrição</th><th>Fornecedor</th>
                        <th class="money">Valor</th><th class="money">Valor em BRL</th><th>Rateio</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="nowrap">{{ $expense->expense_date->format('d/m/Y') }}</td>
                        <td>{{ $expense->description }}</td>
                        <td>{{ $expense->supplier }}</td>
                        <td class="money">{{ $expense->currency }} {{ number_format($expense->amount_cents / 100, 2, ',', '.') }}</td>
                        <td class="money">
                            @if ($expense->isPending())
                                <span class="badge badge-pending">Aguardando cotação</span>
                            @else
                                R$ {{ number_format($expense->amount_brl_cents / 100, 2, ',', '.') }}
                                @if ($expense->currency === 'USD')
                                    <br><small>cotação {{ number_format($expense->exchange_rate, 4, ',', '.') }} ({{ $expense->exchange_rate_date->format('d/m/Y') }})</small>
                                @endif
                            @endif
                        </td>
                        <td>
                            <ul class="allocations">
                                @foreach ($expense->allocations as $allocation)
                                    <li>
                                        {{ $allocation->unit->name }} · {{ $allocation->percentage_bp / 100 }}%
                                        @unless (is_null($allocation->amount_brl_cents))
                                            · <strong>R$ {{ number_format($allocation->amount_brl_cents / 100, 2, ',', '.') }}</strong>
                                        @endunless
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}"
                                  onsubmit="return confirm('Excluir esta despesa?')" style="margin:0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="outline secondary btn-sm">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Nenhuma despesa cadastrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($expenses->hasPages())
        <div class="page-header">
            <div>@if ($expenses->previousPageUrl())<a href="{{ $expenses->previousPageUrl() }}">« Anterior</a>@endif</div>
            <div>@if ($expenses->nextPageUrl())<a href="{{ $expenses->nextPageUrl() }}">Próxima »</a>@endif</div>
        </div>
    @endif
@endsection