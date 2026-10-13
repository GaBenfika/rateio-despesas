<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Gabriel Benfica',
            'email' => 'benficaga04@gmail.com',
            'password' => bcrypt('password'),
        ]);

        $alfa = Company::create(['name' => 'Empresa Alfa']);
        $beta = Company::create(['name' => 'Empresa Beta']);

        $alfa->units()->create(['name' => 'Unidade A']); // código: unidade-a
        $alfa->units()->create(['name' => 'Unidade B']); // código: unidade-b
        $beta->units()->create(['name' => 'Unidade C']); // código: unidade-c
    }
}