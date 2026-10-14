<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_usuario_pelo_terminal_com_senha_em_hash(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Nome', 'Maria Silva')
            ->expectsQuestion('E-mail', 'maria@example.com')
            ->expectsQuestion('Senha', 'senha-segura')
            ->expectsOutputToContain('Usuário maria@example.com criado.')
            ->assertSuccessful();

        $user = User::where('email', 'maria@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Maria Silva', $user->name);
        $this->assertTrue(Hash::check('senha-segura', $user->password));
    }

    #[DataProvider('respostasInvalidas')]
    public function test_recusa_email_ou_senha_invalidos(string $email, string $password, string $message): void
    {
        User::factory()->create(['email' => 'existente@example.com']);

        $command = $this->artisan('user:create')
            ->expectsQuestion('Nome', 'Maria Silva')
            ->expectsQuestion('E-mail', $email);

        if ($password !== '') {
            $command->expectsQuestion('Senha', $password);
        }

        // No terminal o comando pergunta de novo; nos testes o Laravel encerra com falha
        $command->expectsOutputToContain($message)->assertFailed();

        $this->assertSame(1, User::count()); // só o usuário que já existia
    }

    public static function respostasInvalidas(): array
    {
        return [
            'e-mail inválido' => ['email-invalido', '', 'Informe um e-mail válido.'],
            'e-mail já cadastrado' => ['existente@example.com', '', 'Já existe um usuário com este e-mail.'],
            'senha curta' => ['maria@example.com', 'curta', 'A senha precisa ter pelo menos 8 caracteres.'],
        ];
    }
}
