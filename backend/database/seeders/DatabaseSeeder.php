<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Базовая настройка, безопасная для боевой среды: только роли и права. Демо-данные — DemoSeeder. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesSeeder::class);
    }
}
