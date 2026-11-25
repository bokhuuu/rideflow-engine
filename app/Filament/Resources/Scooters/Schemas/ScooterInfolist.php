<?php

namespace App\Filament\Resources\Scooters\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ScooterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('imei'),
                TextEntry::make('name'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('lat')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('lng')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('battery')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
