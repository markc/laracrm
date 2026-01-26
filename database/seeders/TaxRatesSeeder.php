<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\TaxRate;
use Illuminate\Database\Seeder;

class TaxRatesSeeder extends Seeder
{
    public function run(): void
    {
        $gstCollectedAccount = Account::where('code', '2100')->first();

        $taxRates = [
            [
                'name' => 'GST on Income',
                'code' => 'GST',
                'rate' => 10.00,
                'type' => 'sales',
                'account_id' => $gstCollectedAccount?->id,
                'is_default' => true,
            ],
            [
                'name' => 'GST Free',
                'code' => 'GST-FREE',
                'rate' => 0.00,
                'type' => 'sales',
                'account_id' => null,
                'is_default' => false,
            ],
            [
                'name' => 'GST on Expenses',
                'code' => 'GST-EXP',
                'rate' => 10.00,
                'type' => 'purchase',
                'account_id' => $gstCollectedAccount?->id,
                'is_default' => true,
            ],
            [
                'name' => 'No GST',
                'code' => 'NO-GST',
                'rate' => 0.00,
                'type' => 'purchase',
                'account_id' => null,
                'is_default' => false,
            ],
        ];

        foreach ($taxRates as $rate) {
            TaxRate::firstOrCreate(
                ['code' => $rate['code']],
                $rate
            );
        }
    }
}
