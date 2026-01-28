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
        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
            ]
        );

        // Seed Chart of Accounts and Tax Rates (required base data)
        $this->call([
            ChartOfAccountsSeeder::class,
            TaxRatesSeeder::class,
        ]);
    }

    /**
     * Seed with full demo data for testing.
     * Run with: php artisan db:seed --class=DemoDataSeeder
     */
    public function runWithDemoData(): void
    {
        $this->run();

        $this->call([
            DemoDataSeeder::class,
        ]);
    }
}
