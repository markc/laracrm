<?php

namespace App\Filament\Resources\Accounting\StockLevelResource\Pages;

use App\Filament\Resources\Accounting\StockLevelResource;
use App\Filament\Resources\Accounting\StockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockLevels extends ListRecords
{
    protected static string $resource = StockLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('new_movement')
                ->label('Record Movement')
                ->url(StockMovementResource::getUrl('create'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
