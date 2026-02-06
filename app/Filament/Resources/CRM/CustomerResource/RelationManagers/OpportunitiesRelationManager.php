<?php

namespace App\Filament\Resources\CRM\CustomerResource\RelationManagers;

use App\Enums\OpportunityStage;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;

class OpportunitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'opportunities';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('value')
                    ->numeric()
                    ->prefix('$'),
                Forms\Components\TextInput::make('probability')
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(50),
                Forms\Components\Select::make('stage')
                    ->options(OpportunityStage::class)
                    ->required()
                    ->default('lead')
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $stage = OpportunityStage::from($state);
                            $set('probability', $stage->probability());
                        }
                    }),
                Forms\Components\DatePicker::make('expected_close_date'),
                Forms\Components\Select::make('assigned_to')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Columns\TextColumn::make('name')
                    ->searchable(),
                Columns\TextColumn::make('value')
                    ->money('AUD')
                    ->sortable(),
                Columns\TextColumn::make('probability')
                    ->suffix('%'),
                Columns\TextColumn::make('stage')
                    ->badge(),
                Columns\TextColumn::make('expected_close_date')
                    ->date()
                    ->sortable(),
                Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To'),
            ])
            ->filters([
                Filters\SelectFilter::make('stage')
                    ->options(OpportunityStage::class),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('won')
                    ->icon('heroicon-o-trophy')
                    ->color('success')
                    ->visible(fn ($record) => $record->is_open)
                    ->action(fn ($record) => $record->update([
                        'stage' => OpportunityStage::Won,
                        'won_at' => now(),
                        'probability' => 100,
                    ])),
                Actions\Action::make('lost')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->is_open)
                    ->form([
                        Forms\Components\TextInput::make('lost_reason')
                            ->required(),
                    ])
                    ->action(fn ($record, array $data) => $record->update([
                        'stage' => OpportunityStage::Lost,
                        'lost_at' => now(),
                        'lost_reason' => $data['lost_reason'],
                        'probability' => 0,
                    ])),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
