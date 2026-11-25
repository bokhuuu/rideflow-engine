<?php

namespace App\Filament\Resources\Scooters\Schemas;

use App\Enums\ScooterStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ScooterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('imei')
                    ->required()
                    ->maxLength(15)
                    ->minLength(15),
                TextInput::make('name')
                    ->required(),
                Select::make('status')
                    ->options(ScooterStatus::class)
                    ->default(ScooterStatus::Offline)
                    ->required()
            ]);
    }
}
