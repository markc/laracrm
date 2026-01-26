<?php

namespace App\Filament\Resources\Accounting\InvoiceResource\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Accounting\InvoiceResource;
use App\Services\Accounting\InvoiceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === InvoiceStatus::Draft),
            Actions\Action::make('send')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => $this->record->status === InvoiceStatus::Draft)
                ->requiresConfirmation()
                ->action(function () {
                    app(InvoiceService::class)->sendInvoice($this->record);
                    Notification::make()
                        ->title('Invoice sent successfully')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status']);
                }),
            Actions\Action::make('download')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('invoices.pdf', $this->record), shouldOpenInNewTab: true),
        ];
    }
}
