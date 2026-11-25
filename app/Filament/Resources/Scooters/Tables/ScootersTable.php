<?php

namespace App\Filament\Resources\Scooters\Tables;

use App\Enums\ScooterStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use App\Models\Scooter;

class ScootersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('imei')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('battery')
                    ->numeric()
                    ->sortable()
                    ->suffix('%'),
                TextColumn::make('lat')
                    ->label('Location')
                    ->formatStateUsing(
                        fn(Scooter $record): string =>
                        $record->lat && $record->lng
                            ? "{$record->lat}, {$record->lng}"
                            : '-'
                    )
                    ->url(
                        fn(Scooter $record): ?string =>
                        $record->lat && $record->lng
                            // ?q=lat,lng drops a pin at that exact location.
                            ? "https://maps.google.com/?q={$record->lat},{$record->lng}"
                            : null
                    )
                    ->openUrlInNewTab(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),

                // ── Beep ───────────────────────────────────────
                Action::make('beep')
                    ->label('Beep')
                    ->icon('heroicon-o-speaker-wave')
                    ->color('info')

                    ->visible(
                        fn(Scooter $record): bool =>
                        $record->status === ScooterStatus::Available ||
                            $record->status === ScooterStatus::InRide
                    )

                    ->action(function (Scooter $record): void {
                        $response = Http::timeout(10)->post(
                            config('scooter.tcp_server_url') . '/scooter/command',
                            [
                                'imei'   => $record->imei,
                                'action' => 'beep',
                            ]
                        );

                        if ($response->successful() && $response->json('success')) {
                            Notification::make()
                                ->title('Beep sent')
                                ->body("V0 command sent to {$record->imei}")
                                ->success()
                                ->send();
                        } else {
                            $reason = $response->json('message') ?? 'No response from TCP server';

                            Notification::make()
                                ->title('Beep failed')
                                ->body($reason)
                                ->danger()
                                ->send();
                        }
                    }),

                // ── Restore Speed Limits ──────────────────────────────────
                Action::make('restore_speed')
                    ->label('Restore Speeds')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')

                    ->requiresConfirmation()
                    ->modalHeading('Restore Speed Limits')
                    ->modalDescription(
                        'This sends an S4 command to the scooter and restores ' .
                            'normal speed limits: 15 / 20 / 25 km/h. ' .
                            'Use this after physically recovering a scooter that ' .
                            'was locked outside a zone.'
                    )
                    ->modalSubmitActionLabel('Yes, restore speeds')

                    ->visible(
                        fn(Scooter $record): bool =>
                        $record->status === ScooterStatus::InRide &&
                            $record->rides()
                            ->where('status', 'active')
                            ->whereNotNull('zone_violated_at')
                            ->exists()
                    )

                    ->action(function (Scooter $record): void {
                        $response = Http::timeout(10)->post(
                            config('scooter.tcp_server_url') . '/scooter/command',
                            [
                                'imei'   => $record->imei,
                                'action' => 'restore_speed',
                            ]
                        );

                        if ($response->successful() && $response->json('success')) {
                            Notification::make()
                                ->title('Speed limits restored')
                                ->body("S4 sent to {$record->imei} — limits: 15 / 20 / 25 km/h")
                                ->success()
                                ->send();
                        } else {
                            $reason = $response->json('message') ?? 'No response from TCP server';

                            Notification::make()
                                ->title('Command failed')
                                ->body($reason)
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }
}
