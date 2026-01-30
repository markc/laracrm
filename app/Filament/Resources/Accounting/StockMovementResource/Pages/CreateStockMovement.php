<?php

namespace App\Filament\Resources\Accounting\StockMovementResource\Pages;

use App\Filament\Resources\Accounting\StockMovementResource;
use App\Models\Accounting\Product;
use App\Services\Accounting\InventoryService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateStockMovement extends CreateRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(InventoryService::class);
        $product = Product::findOrFail($data['product_id']);

        return match ($data['movement_type']) {
            'receipt' => $service->receiveStock(
                product: $product,
                quantity: $data['quantity'],
                locationId: $data['to_location_id'],
                notes: $data['notes'] ?? null
            ),
            'shipment' => $service->shipStock(
                product: $product,
                quantity: $data['quantity'],
                locationId: $data['from_location_id'],
                notes: $data['notes'] ?? null
            ),
            'transfer' => $service->transferStock(
                product: $product,
                quantity: $data['quantity'],
                fromLocationId: $data['from_location_id'],
                toLocationId: $data['to_location_id'],
                notes: $data['notes'] ?? null
            ),
            'adjustment' => $this->handleAdjustment($service, $product, $data),
            'return' => $service->returnStock(
                product: $product,
                quantity: $data['quantity'],
                locationId: $data['to_location_id'],
                notes: $data['notes'] ?? null
            ),
            default => throw new \Exception("Unknown movement type: {$data['movement_type']}"),
        };
    }

    protected function handleAdjustment(InventoryService $service, Product $product, array $data): \Illuminate\Database\Eloquent\Model
    {
        $locationId = $data['adjustment_location_id'];
        $currentStock = $product->getStockAtLocation($locationId);
        $newQuantity = $currentStock + $data['quantity']; // Quantity entered is the adjustment amount

        return $service->adjustStock(
            product: $product,
            newQuantity: $newQuantity,
            locationId: $locationId,
            notes: $data['notes'] ?? null
        );
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Stock movement recorded successfully';
    }

    protected function onValidationError(\Illuminate\Validation\ValidationException $exception): void
    {
        Notification::make()
            ->title('Validation Error')
            ->danger()
            ->send();
    }
}
