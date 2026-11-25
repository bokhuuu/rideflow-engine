<?php

namespace App\Filament\Resources\Scooters\Pages;

use App\Filament\Resources\Scooters\ScooterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewScooter extends ViewRecord
{
    protected static string $resource = ScooterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
