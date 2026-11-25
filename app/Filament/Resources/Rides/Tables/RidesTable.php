<?php

namespace App\Filament\Resources\Rides\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scooter.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_session_id')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->numeric()
                    ->sortable()
                    ->suffix(' min'),
                TextColumn::make('cost')
                    ->money('GEL')
                    ->sortable(),
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
