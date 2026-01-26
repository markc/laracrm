<?php

namespace App\Filament\Resources\Accounting\InvoiceResource\Pages;

use App\Filament\Resources\Accounting\InvoiceResource;
use App\Services\Accounting\InvoiceService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Recalculate totals after saving
        app(InvoiceService::class)->recalculateInvoice($this->record);
    }
}
