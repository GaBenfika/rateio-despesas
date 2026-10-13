<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rateio de Despesas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2.0.6/css/pico.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

@auth
    <aside class="sidebar">
        <div class="brand">
            Rateio de Despesas
            <small>Grupo de empresas</small>
        </div>

        <div class="menu">
            <div class="menu-title">Despesas</div>
            <a href="{{ route('expenses.index') }}" @class(['active' => request()->routeIs('expenses.index')])>📄 Lista de despesas</a>
            <a href="{{ route('expenses.create') }}" @class(['active' => request()->routeIs('expenses.create')])>➕ Nova despesa</a>
            <a href="{{ route('expenses.import') }}" @class(['active' => request()->routeIs('expenses.import*')])>📥 Importar CSV</a>

            <div class="menu-title">Cadastros</div>
            <a href="{{ route('units.index') }}" @class(['active' => request()->routeIs('units.*')])>🏢 Unidades</a>

            <div class="menu-title">Relatórios</div>
            <a href="{{ route('reports.units') }}" @class(['active' => request()->routeIs('reports.*')])>📊 Total por unidade</a>
        </div>

        <div class="user">
            <strong>{{ auth()->user()->name }}</strong>
            {{ auth()->user()->email }}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Sair</button>
            </form>
        </div>
    </aside>

    <main class="content">
        @include('layouts.messages')
        @yield('content')
    </main>
@else
    <main class="login-wrapper">
        <div class="card">
            @include('layouts.messages')
            @yield('content')
        </div>
    </main>
@endauth

</body>
</html>