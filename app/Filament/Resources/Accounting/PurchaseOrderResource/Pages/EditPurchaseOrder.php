<?php

namespace App\Filament\Resources\Accounting\PurchaseOrderResource\Pages;

use App\Filament\Resources\Accounting\PurchaseOrderResource;
use App\Services\Accounting\PurchaseOrderService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(PurchaseOrderService::class)->recalculateOrder($this->record);
    }
}
