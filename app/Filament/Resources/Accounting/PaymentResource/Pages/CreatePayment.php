<?php

namespace App\Filament\Resources\Accounting\PaymentResource\Pages;

use App\Filament\Resources\Accounting\PaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['allocated_amount'] = 0;
        $data['unallocated_amount'] = $data['amount'];

        return $data;
    }
}
