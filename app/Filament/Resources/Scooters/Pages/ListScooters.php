<?php

namespace App\Filament\Resources\Scooters\Pages;

use App\Filament\Resources\Scooters\ScooterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScooters extends ListRecords
{
    protected static string $resource = ScooterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
