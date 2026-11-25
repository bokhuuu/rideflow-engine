<?php

namespace App\Filament\Resources\Rides\Schemas;

use App\Enums\RideStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('scooter_id')
                    ->relationship('scooter', 'name')
                    ->required(),
                TextInput::make('payment_session_id')
                    ->required(),
                DateTimePicker::make('started_at')
                    ->required(),
                DateTimePicker::make('ended_at'),
                TextInput::make('duration_minutes')
                    ->numeric()
                    ->suffix('min'),
                TextInput::make('cost')
                    ->numeric()
                    ->prefix('₾'),
                Select::make('status')
                    ->options(RideStatus::class)
                    ->default(RideStatus::Active)
                    ->required(),
            ]);
    }
}
