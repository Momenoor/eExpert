<?php

namespace Database\Seeders;

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
        // Kept as a single call to the seeder the installer itself runs, so a
        // plain `php artisan migrate --seed` in local development ends up with
        // exactly the same essential data a real deployment gets — nothing
        // dev-only has been added here, so there is currently no difference,
        // but the indirection means one never silently drifts from the other.
        $this->call(ProductionDatabaseSeeder::class);
    }
}
