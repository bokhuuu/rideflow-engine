<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('ride_id')
                    ->relationship('ride', 'id')
                    ->required(),
                Select::make('provider')
                    ->options(['tbc' => 'TBC', 'bog' => 'BOG'])
                    ->required(),
                TextInput::make('payment_session_id')
                    ->required(),
                TextInput::make('hold_amount')
                    ->required()
                    ->numeric()
                    ->prefix('₾'),
                TextInput::make('captured_amount')
                    ->numeric()
                    ->prefix('₾'),
                TextInput::make('hold_transaction_id'),
                TextInput::make('capture_transaction_id'),
                Select::make('status')
                    ->options(PaymentStatus::class)
                    ->default(PaymentStatus::Pending)
                    ->required(),
            ]);
    }
}
