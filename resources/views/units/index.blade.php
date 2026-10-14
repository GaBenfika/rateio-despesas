@extends('layouts.app')

@section('content')
    <div class="page-header">
        <h2>Unidades</h2>
    </div>

    <div class="card">
        <h4>Nova unidade</h4>
        <form method="POST" action="{{ route('units.store') }}">
            @csrf
            <div class="grid">
                <label>Empresa
                    <select name="company_id" required>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Nome
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
                </label>
                <label>Código <small>(opcional, usado no CSV)</small>
                    <input type="text" name="code" value="{{ old('code') }}" maxlength="255" placeholder="gerado a partir do nome">
                </label>
            </div>
            <button type="submit">Cadastrar unidade</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Unidade</th><th>Código</th><th>Empresa</th></tr></thead>
                <tbody>
                @foreach ($units as $unit)
                    <tr><td>{{ $unit->name }}</td><td><code>{{ $unit->code }}</code></td><td>{{ $unit->company->name }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection