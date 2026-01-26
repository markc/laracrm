<?php

namespace App\Filament\Resources\Accounting\InvoiceResource\Pages;

use App\Filament\Resources\Accounting\InvoiceResource;
use App\Services\Accounting\InvoiceService;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Recalculate totals after creation
        app(InvoiceService::class)->recalculateInvoice($this->record);
    }
}
