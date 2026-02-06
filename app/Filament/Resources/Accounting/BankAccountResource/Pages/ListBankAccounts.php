<?php

namespace App\Filament\Resources\Accounting\BankAccountResource\Pages;

use App\Filament\Resources\Accounting\BankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankAccounts extends ListRecords
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
