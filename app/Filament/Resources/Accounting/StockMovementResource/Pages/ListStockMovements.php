<?php

namespace App\Filament\Resources\Accounting\StockMovementResource\Pages;

use App\Filament\Resources\Accounting\StockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Movement'),
        ];
    }
}
