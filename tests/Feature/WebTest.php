<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/expenses')->assertRedirect('/login');
    }

    public function test_paginas_principais_carregam(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/expenses', '/expenses/create', '/expenses/import', '/units', '/reports/units'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_cria_despesa_pelo_formulario_ignorando_unidades_em_branco(): void
    {
        $company = Company::create(['name' => 'Empresa Teste']);
        $a = $company->units()->create(['name' => 'Unidade A']);
        $b = $company->units()->create(['name' => 'Unidade B']);
        $c = $company->units()->create(['name' => 'Unidade C']);

        $this->actingAs(User::factory()->create())
            ->post('/expenses', [
                'description' => 'Internet', 'supplier' => 'Fornecedor Y', 'expense_date' => '2026-09-01',
                'currency' => 'BRL', 'amount' => '100.00',
                'allocations' => [$a->id => '50', $b->id => '50', $c->id => ''], // C fica fora
            ])
            ->assertRedirect('/expenses');

        $this->assertSame(2, Expense::first()->allocations()->count());
    }
}