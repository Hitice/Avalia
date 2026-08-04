<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Um seeder por modulo, na ordem de dependencia.
        // Nao existe dado de exemplo: a plataforma opera so com dado real.
        $this->call([
            AcessoSeeder::class,
            CatalogoSeeder::class,
            CustosSeeder::class,
            PlanosSeeder::class,
        ]);
    }
}
