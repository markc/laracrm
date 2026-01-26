<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Accounting\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets (1000-1999)
            ['code' => '1000', 'name' => 'Cash on Hand', 'type' => AccountType::Asset],
            ['code' => '1010', 'name' => 'Bank Account - Operating', 'type' => AccountType::Asset],
            ['code' => '1020', 'name' => 'Bank Account - Savings', 'type' => AccountType::Asset],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset, 'is_system' => true],
            ['code' => '1300', 'name' => 'Prepaid Expenses', 'type' => AccountType::Asset],
            ['code' => '1500', 'name' => 'Computer Equipment', 'type' => AccountType::Asset],
            ['code' => '1510', 'name' => 'Accumulated Depreciation - Equipment', 'type' => AccountType::Asset],

            // Liabilities (2000-2999)
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => AccountType::Liability, 'is_system' => true],
            ['code' => '2100', 'name' => 'GST Collected', 'type' => AccountType::Liability, 'is_system' => true],
            ['code' => '2110', 'name' => 'GST Paid', 'type' => AccountType::Liability, 'is_system' => true],
            ['code' => '2200', 'name' => 'PAYG Withholding Payable', 'type' => AccountType::Liability],
            ['code' => '2300', 'name' => 'Superannuation Payable', 'type' => AccountType::Liability],
            ['code' => '2400', 'name' => 'Credit Cards Payable', 'type' => AccountType::Liability],
            ['code' => '2500', 'name' => 'Loans Payable', 'type' => AccountType::Liability],

            // Equity (3000-3999)
            ['code' => '3000', 'name' => "Owner's Equity", 'type' => AccountType::Equity],
            ['code' => '3100', 'name' => "Owner's Drawings", 'type' => AccountType::Equity],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => AccountType::Equity, 'is_system' => true],

            // Revenue (4000-4999)
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => AccountType::Revenue, 'is_system' => true],
            ['code' => '4100', 'name' => 'Service Revenue', 'type' => AccountType::Revenue],
            ['code' => '4200', 'name' => 'Hosting Revenue', 'type' => AccountType::Revenue],
            ['code' => '4300', 'name' => 'Domain Revenue', 'type' => AccountType::Revenue],
            ['code' => '4900', 'name' => 'Other Income', 'type' => AccountType::Revenue],

            // Expenses (5000-5999)
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => AccountType::Expense],
            ['code' => '5100', 'name' => 'Server Costs', 'type' => AccountType::Expense],
            ['code' => '5200', 'name' => 'Domain Costs', 'type' => AccountType::Expense],
            ['code' => '5300', 'name' => 'Bandwidth Costs', 'type' => AccountType::Expense],
            ['code' => '6000', 'name' => 'Advertising & Marketing', 'type' => AccountType::Expense],
            ['code' => '6100', 'name' => 'Bank Fees', 'type' => AccountType::Expense],
            ['code' => '6200', 'name' => 'Depreciation Expense', 'type' => AccountType::Expense],
            ['code' => '6300', 'name' => 'Insurance', 'type' => AccountType::Expense],
            ['code' => '6400', 'name' => 'Interest Expense', 'type' => AccountType::Expense],
            ['code' => '6500', 'name' => 'Office Supplies', 'type' => AccountType::Expense],
            ['code' => '6600', 'name' => 'Professional Fees', 'type' => AccountType::Expense],
            ['code' => '6700', 'name' => 'Rent', 'type' => AccountType::Expense],
            ['code' => '6800', 'name' => 'Software Subscriptions', 'type' => AccountType::Expense],
            ['code' => '6900', 'name' => 'Telephone & Internet', 'type' => AccountType::Expense],
            ['code' => '7000', 'name' => 'Travel & Entertainment', 'type' => AccountType::Expense],
            ['code' => '7100', 'name' => 'Utilities', 'type' => AccountType::Expense],
            ['code' => '7200', 'name' => 'Wages & Salaries', 'type' => AccountType::Expense],
            ['code' => '7300', 'name' => 'Superannuation Expense', 'type' => AccountType::Expense],
        ];

        foreach ($accounts as $account) {
            Account::firstOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'normal_balance' => $account['type']->normalBalance(),
                    'is_system' => $account['is_system'] ?? false,
                    'is_active' => true,
                ]
            );
        }
    }
}
