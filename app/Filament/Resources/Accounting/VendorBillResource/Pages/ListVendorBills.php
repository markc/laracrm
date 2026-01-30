<?php

namespace App\Filament\Resources\Accounting\VendorBillResource\Pages;

use App\Filament\Resources\Accounting\VendorBillResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendorBills extends ListRecords
{
    protected static string $resource = VendorBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
