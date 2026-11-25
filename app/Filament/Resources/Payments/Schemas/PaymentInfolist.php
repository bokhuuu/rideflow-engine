<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('ride_id')
                    ->numeric(),
                TextEntry::make('provider')
                    ->badge(),
                TextEntry::make('payment_session_id'),
                TextEntry::make('hold_amount')
                    ->numeric(),
                TextEntry::make('captured_amount')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('hold_transaction_id')
                    ->placeholder('-'),
                TextEntry::make('capture_transaction_id')
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
