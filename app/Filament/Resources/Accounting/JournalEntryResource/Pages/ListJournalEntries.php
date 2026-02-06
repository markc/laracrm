<?php

namespace App\Filament\Resources\Accounting\JournalEntryResource\Pages;

use App\Filament\Resources\Accounting\JournalEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;
}
