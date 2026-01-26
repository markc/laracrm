<?php

namespace App\Filament\Widgets;

use App\Models\CRM\Activity;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingActivitiesTable extends BaseWidget
{
    protected static ?int $sort = 6;

    protected static ?string $heading = 'Upcoming Activities';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->with(['customer', 'assignedUser'])
                    ->pending()
                    ->whereNotNull('due_date')
                    ->orderBy('due_date')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('subject')
                    ->limit(30),
                TextColumn::make('customer.company_name')
                    ->label('Customer'),
                TextColumn::make('due_date')
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($record) => $record->is_overdue ? 'danger' : null),
                TextColumn::make('assignedUser.name')
                    ->label('Assigned To'),
            ])
            ->recordActions([
                Action::make('complete')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['completed_at' => now()])),
            ])
            ->paginated(false);
    }
}
