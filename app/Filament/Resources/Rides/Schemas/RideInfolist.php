<?php

namespace App\Filament\Resources\Rides\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RideInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('scooter.name')
                    ->label('Scooter'),
                TextEntry::make('payment_session_id'),
                TextEntry::make('started_at')
                    ->dateTime(),
                TextEntry::make('ended_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('duration_minutes')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('start_lat')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('start_lng')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('end_lat')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('end_lng')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('cost')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
