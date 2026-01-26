<?php

namespace App\Filament\Resources\CRM\OpportunityResource\Pages;

use App\Filament\Resources\CRM\OpportunityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOpportunity extends EditRecord
{
    protected static string $resource = OpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
