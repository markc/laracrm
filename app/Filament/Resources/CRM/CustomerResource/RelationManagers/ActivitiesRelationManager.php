<?php

namespace App\Filament\Resources\CRM\CustomerResource\RelationManagers;

use App\Enums\ActivityType;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('type')
                    ->options(ActivityType::class)
                    ->required(),
                Forms\Components\TextInput::make('subject')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('activity_date')
                    ->required()
                    ->default(now()),
                Forms\Components\DateTimePicker::make('due_date'),
                Forms\Components\Select::make('contact_id')
                    ->relationship('contact', 'first_name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('assigned_to')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->columns([
                Columns\TextColumn::make('type')
                    ->badge(),
                Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(40),
                Columns\TextColumn::make('activity_date')
                    ->dateTime()
                    ->sortable(),
                Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To'),
                Columns\IconColumn::make('is_completed')
                    ->boolean()
                    ->label('Done'),
            ])
            ->filters([
                Filters\SelectFilter::make('type')
                    ->options(ActivityType::class),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('complete')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->completed_at === null)
                    ->action(fn ($record) => $record->update(['completed_at' => now()])),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('activity_date', 'desc');
    }
}
