<?php

namespace App\Filament\Resources\Rides\Pages;

use App\Filament\Resources\Rides\RideResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use App\Enums\RideStatus;
use App\Enums\ScooterStatus;

class ViewRide extends ViewRecord
{
    protected static string $resource = RideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recover_ride')
                ->label('Recover Ride')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('danger')

                ->requiresConfirmation()
                ->modalHeading('Recover This Ride')
                ->modalDescription(
                    'Use this when the scooter was locked outside a zone and ' .
                        'staff have physically recovered it. This will: restore ' .
                        'normal speed limits, unlock the scooter, and mark the ' .
                        'ride as completed.'
                )
                ->modalSubmitActionLabel('Yes, recover ride')

                ->visible(fn(): bool => $this->record->status === RideStatus::Active)

                ->action(function (): void {
                    $ride = $this->record;
                    $scooter = $ride->scooter;
                    $imei = $scooter->imei;

                    $speedResponse = Http::timeout(10)->post(
                        config('scooter.tcp_server_url') . '/scooter/command',
                        [
                            'imei' => $imei,
                            'action' => 'restore_speed',
                        ]
                    );

                    if (! $speedResponse->successful() || ! $speedResponse->json('success')) {
                        \Log::warning('Recover ride: speed restore failed', [
                            'ride_id' => $ride->id,
                            'imei' => $imei,
                            'reason' => $speedResponse->json('message') ?? 'no response',
                        ]);
                    }

                    $unlockResponse = Http::timeout(10)->post(
                        config('scooter.tcp_server_url') . '/scooter/command',
                        [
                            'imei' => $imei,
                            'action' => 'unlock',
                            'user_id' => 0,
                        ]
                    );

                    if (! $unlockResponse->successful() || ! $unlockResponse->json('success')) {
                        \Log::warning('Recover ride: unlock failed', [
                            'ride_id' => $ride->id,
                            'imei' => $imei,
                            'reason' => $unlockResponse->json('message') ?? 'no response',
                        ]);
                    }

                    $endedAt = now();

                    $durationMinutes = $ride->started_at
                        ? (int) $ride->started_at->diffInMinutes($endedAt)
                        : 0;

                    $ride->update([
                        'status' => RideStatus::Completed,
                        'ended_at' => $endedAt,
                        'duration_minutes' => $durationMinutes,
                    ]);

                    $scooter->update([
                        'status' => ScooterStatus::Available,
                    ]);

                    Notification::make()
                        ->title('Ride recovered')
                        ->body("Scooter {$imei} unlocked and marked available. Ride closed.")
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'ended_at',
                        'duration_minutes',
                    ]);
                }),
        ];
    }
}
