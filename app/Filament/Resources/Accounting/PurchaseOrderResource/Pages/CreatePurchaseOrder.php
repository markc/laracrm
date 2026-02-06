<?php

namespace App\Filament\Resources\Accounting\PurchaseOrderResource\Pages;

use App\Filament\Resources\Accounting\PurchaseOrderResource;
use App\Services\Accounting\PurchaseOrderService;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(PurchaseOrderService::class)->recalculateOrder($this->record);
    }
}
