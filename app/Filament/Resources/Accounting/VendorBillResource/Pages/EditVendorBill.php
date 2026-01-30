<?php

namespace App\Filament\Resources\Accounting\VendorBillResource\Pages;

use App\Filament\Resources\Accounting\VendorBillResource;
use App\Services\Accounting\VendorBillService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVendorBill extends EditRecord
{
    protected static string $resource = VendorBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Recalculate totals after items are saved
        app(VendorBillService::class)->recalculateBill($this->record);
    }
}
