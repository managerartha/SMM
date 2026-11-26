<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BaseDataSeeder::class);
        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
            $this->call(DemoPhase2Seeder::class);
            $this->call(DemoPhase3Seeder::class);
            $this->call(DemoPhase4Seeder::class);
        }
    }
}
