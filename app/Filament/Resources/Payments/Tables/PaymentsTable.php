<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ride.scooter.name')
                    ->label('Scooter')
                    ->searchable(),
                TextColumn::make('provider')
                    ->badge(),
                TextColumn::make('payment_session_id')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('hold_amount')
                    ->money('GEL')
                    ->sortable(),
                TextColumn::make('captured_amount')
                    ->money('GEL')
                    ->sortable(),
                TextColumn::make('hold_transaction_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('capture_transaction_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
