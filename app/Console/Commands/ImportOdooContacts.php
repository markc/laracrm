<?php

namespace App\Console\Commands;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;

class ImportOdooContacts extends Command
{
    protected $signature = 'import:odoo-contacts {file : Path to CSV file}';

    protected $description = 'Import contacts from Odoo CSV export';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            // Skip if no name
            if (empty($data['name'])) {
                $skipped++;

                continue;
            }

            // Check for existing customer by email
            if (! empty($data['email']) && Customer::where('email', $data['email'])->exists()) {
                $this->line("Skipping duplicate: {$data['name']} ({$data['email']})");
                $skipped++;

                continue;
            }

            $isCompany = $data['is_company'] === 't';

            // Parse name for individuals
            $firstName = null;
            $lastName = null;
            $companyName = null;

            if ($isCompany) {
                $companyName = $data['name'];
            } else {
                $nameParts = explode(' ', $data['name'], 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
            }

            // Build billing address
            $billingAddress = array_filter([
                'street' => trim(($data['street'] ?? '').' '.($data['street2'] ?? '')),
                'city' => $data['city'] ?? null,
                'state' => 'QLD', // Default for Australian contacts
                'postcode' => $data['zip'] ?? null,
                'country' => 'Australia',
            ]);

            // Clean phone number (prefer phone over mobile)
            $phone = $data['phone'] ?: $data['mobile'] ?: null;

            // Clean HTML from comments
            $notes = $data['comment'] ?? null;
            if ($notes) {
                $notes = strip_tags($notes);
                $notes = trim($notes);
            }

            // Generate customer number
            $customerNumber = 'ODOO-'.str_pad($data['id'], 4, '0', STR_PAD_LEFT);

            Customer::create([
                'customer_number' => $customerNumber,
                'company_name' => $companyName,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $data['email'] ?: null,
                'phone' => $phone,
                'type' => $isCompany ? CustomerType::Company : CustomerType::Individual,
                'billing_address' => ! empty($billingAddress) ? $billingAddress : null,
                'status' => CustomerStatus::Active,
                'notes' => $notes ?: null,
            ]);

            $imported++;
            $this->line("Imported: {$data['name']}");
        }

        fclose($handle);

        $this->newLine();
        $this->info("Import complete: {$imported} imported, {$skipped} skipped");

        return self::SUCCESS;
    }
}
