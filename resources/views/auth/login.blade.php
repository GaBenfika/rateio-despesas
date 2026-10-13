@extends('layouts.app')

@section('content')
    <h2>Entrar</h2>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label>E-mail
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        </label>
        <label>Senha
            <input type="password" name="password" required>
        </label>
        <button type="submit" style="width: 100%">Entrar</button>
    </form>
@endsection