<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Compte unique de l'artiste. Le mot de passe vient de la factory : a changer
        // avant toute mise en ligne.
        User::factory()->create([
            'name' => 'Tatasasa',
            'email' => 'atelier@tatasasa.test',
        ]);

        $this->call(PigmentSeeder::class);
    }
}
