@extends('layouts.app')

@section('content')
    <div class="page-header">
        <h2>Total por unidade</h2>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('reports.units') }}">
            <div class="grid">
                <label>De <input type="date" name="from" value="{{ $report['from'] }}" required></label>
                <label>Até <input type="date" name="to" value="{{ $report['to'] }}" required></label>
                <label>&nbsp;<button type="submit" style="width:100%">Filtrar</button></label>
            </div>
        </form>
    </div>

    @if ($report['pending_expenses'] > 0)
        <div class="alert alert-warning">
            {{ $report['pending_expenses'] }} despesa(s) em USD aguardando cotação ainda não entram no total.
        </div>
    @endif

    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Unidade</th><th class="money">Total em BRL</th></tr></thead>
                <tbody>
                @forelse ($report['units'] as $row)
                    <tr>
                        <td>{{ $row['unit'] }}</td>
                        <td class="money">R$ {{ number_format($row['total_brl_cents'] / 100, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">Nenhuma despesa no período.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection