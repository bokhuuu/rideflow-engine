<?php

namespace App\Filament\Resources\Scooters\Pages;

use App\Filament\Resources\Scooters\ScooterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditScooter extends EditRecord
{
    protected static string $resource = ScooterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
