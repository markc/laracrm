<?php

namespace App\Filament\Resources\Accounting\VendorBillResource\Pages;

use App\Filament\Resources\Accounting\VendorBillResource;
use App\Services\Accounting\VendorBillService;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorBill extends CreateRecord
{
    protected static string $resource = VendorBillResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['balance_due'] = 0; // Will be calculated after items

        return $data;
    }

    protected function afterCreate(): void
    {
        // Recalculate totals after items are saved
        app(VendorBillService::class)->recalculateBill($this->record);
    }
}
