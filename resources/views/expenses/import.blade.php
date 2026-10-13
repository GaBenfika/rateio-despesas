@extends('layouts.app')

@section('content')
    <div class="page-header">
        <h2>Importar despesas (CSV)</h2>
    </div>

    <div class="card">
        <p>Formato: <code>data;descricao;fornecedor;valor;moeda;rateio</code><br>
           Exemplo: <code>2026-09-01;Licença CRM;Fornecedor X;1500.00;USD;unidade-a:50|unidade-b:30|unidade-c:20</code></p>

        <form method="POST" action="{{ route('expenses.import.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="file" name="file" accept=".csv,text/csv" required>
            <button type="submit">Importar</button>
        </form>
    </div>

    @isset($result)
        <div class="alert {{ $result['errors'] ? 'alert-warning' : 'alert-success' }}">
            <strong>{{ $result['imported'] }}</strong> linha(s) importada(s),
            <strong>{{ count($result['errors']) }}</strong> com erro.
        </div>

        @if ($result['errors'])
            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Linha</th><th>Motivo</th></tr></thead>
                        <tbody>
                        @foreach ($result['errors'] as $error)
                            <tr><td class="nowrap">{{ $error['line'] }}</td><td>{{ implode(' ', $error['messages']) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endisset
@endsection