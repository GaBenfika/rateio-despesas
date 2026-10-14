<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:create', function () {
    $name = text(label: 'Nome', required: true);

    $email = text(
        label: 'E-mail',
        required: true,
        validate: fn (string $value) => match (true) {
            ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'Informe um e-mail válido.',
            User::where('email', $value)->exists() => 'Já existe um usuário com este e-mail.',
            default => null,
        },
    );

    $password = password(
        label: 'Senha',
        required: true,
        validate: fn (string $value) => strlen($value) < 8 ? 'A senha precisa ter pelo menos 8 caracteres.' : null,
    );

    // O cast 'password' => 'hashed' do model User grava a senha com hash
    User::create(['name' => $name, 'email' => $email, 'password' => $password]);

    info("Usuário {$email} criado. Use esse e-mail e senha no login da tela e da API.");
})->purpose('Cria um usuário para acessar o sistema e a API');
