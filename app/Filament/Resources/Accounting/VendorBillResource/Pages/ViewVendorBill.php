<?php

namespace App\Filament\Resources\Accounting\VendorBillResource\Pages;

use App\Enums\VendorBillStatus;
use App\Filament\Resources\Accounting\VendorBillResource;
use App\Services\Accounting\VendorBillService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorBill extends ViewRecord
{
    protected static string $resource = VendorBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === VendorBillStatus::Draft),

            Actions\Action::make('receive')
                ->label('Receive Bill')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->visible(fn () => $this->record->status === VendorBillStatus::Draft)
                ->requiresConfirmation()
                ->modalHeading('Receive Bill')
                ->modalDescription('This will post the bill to the general ledger. Continue?')
                ->action(function () {
                    app(VendorBillService::class)->receiveBill($this->record);
                    Notification::make()
                        ->title('Bill received and posted to GL')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'received_at']);
                }),

            Actions\Action::make('void')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => ! in_array($this->record->status, [VendorBillStatus::Void, VendorBillStatus::Paid]))
                ->form([
                    \Filament\Forms\Components\TextInput::make('void_reason')
                        ->required()
                        ->label('Reason for voiding'),
                ])
                ->action(function (array $data) {
                    try {
                        app(VendorBillService::class)->voidBill($this->record, $data['void_reason']);
                        Notification::make()
                            ->title('Bill voided successfully')
                            ->success()
                            ->send();
                        $this->refreshFormData(['status', 'voided_at', 'void_reason']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
