<?php

namespace App\Services;

use App\DTOs\ScooterCommandResult;
use App\Exceptions\ScooterException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Python TCP server on behalf of the rest of Laravel.
 *
 * Laravel → HTTP POST → Python :8080 → TCP → OMNI M151 → Scooter
 *
 * Every scooter action (unlock, lock, beep) goes through here.
 * The Python server deals with the raw device protocol - we just tell it what to do.
 */
class ScooterService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = (string) config('scooter.tcp_server_url', 'http://127.0.0.1:8080');
        $this->timeout = (int) config('scooter.timeout', 15);
    }

    /**
     * Unlock a scooter and start a ride.
     *
     * Triggers the R0 → L0 handshake on the Python side:
     * Python sends R0 to get a one-time KEY from the device, then L0 to unlock.
     *
     * @throws ScooterException  If the Python server can't be reached.
     */
    public function unlock(string $imei, int $userId): ScooterCommandResult
    {
        Log::info('ScooterService: unlock requested', ['imei' => $imei, 'user_id' => $userId]);

        return $this->sendCommand($imei, 'unlock', ['user_id' => $userId]);
    }

    /**
     * Lock a scooter and end a ride.
     *
     * Triggers the R0 → L1 handshake. The response includes ride_duration_minutes
     * which comes back from the device in the L1 confirmation.
     *
     * @throws ScooterException  If the Python server can't be reached.
     */
    public function lock(string $imei, int $userId): ScooterCommandResult
    {
        Log::info('ScooterService: lock requested', ['imei' => $imei, 'user_id' => $userId]);

        return $this->sendCommand($imei, 'lock', ['user_id' => $userId]);
    }

    /**
     * Make a scooter beep so the user can find it.
     *
     * Uses the V0 command - no handshake needed, fire and confirm.
     *
     * @throws ScooterException  If the Python server can't be reached.
     */
    public function beep(string $imei): ScooterCommandResult
    {
        Log::info('ScooterService: beep requested', ['imei' => $imei]);

        return $this->sendCommand($imei, 'beep');
    }

    /**
     * Cap all speed modes to 6 km/h - geofence violation warning.
     * S4 command with all limits set to minimum.
     *
     * @throws ScooterException
     */
    public function slowDown(string $imei): ScooterCommandResult
    {
        Log::info('ScooterService: slow down requested', ['imei' => $imei]);

        return $this->sendCommand($imei, 'slow_down');
    }

    /**
     * Restore normal speed limits after geofence grace period is cancelled.
     * S4 command with standard limits: 15/20/25 km/h.
     *
     * @throws ScooterException
     */
    public function restoreSpeed(string $imei): ScooterCommandResult
    {
        Log::info('ScooterService: speed restore requested', ['imei' => $imei]);

        return $this->sendCommand($imei, 'restore_speed');
    }

    /**
     * Start real-time GPS tracking on a scooter.
     * Sends D1 command - scooter begins sending location every 10 seconds.
     * Called when a ride starts.
     *
     * @throws ScooterException
     */
    public function startTracking(string $imei): ScooterCommandResult
    {
        Log::info('ScooterService: tracking start requested', ['imei' => $imei]);

        return $this->sendCommand($imei, 'tracking_start');
    }

    /**
     * Stop real-time GPS tracking on a scooter.
     * Sends D1,0 command - scooter stops sending location packets.
     * Called when a ride ends.
     *
     * @throws ScooterException
     */
    public function stopTracking(string $imei): ScooterCommandResult
    {
        Log::info('ScooterService: tracking stop requested', ['imei' => $imei]);

        return $this->sendCommand($imei, 'tracking_stop');
    }

    /**
     * Send a command to the Python server and return a typed result.
     *
     * All three public methods route through here so HTTP logic lives in one place.
     *
     * Throws ScooterException in two cases:
     *   - Python server is unreachable (ConnectionException)
     *   - Python server returned a non-2xx response
     *
     * A successful HTTP response with success=false is NOT an exception -
     * it means the device responded but refused the command (e.g. already locked).
     *
     * @throws ScooterException
     */
    private function sendCommand(string $imei, string $command, array $params = []): ScooterCommandResult
    {
        $body = array_merge(['imei' => $imei, 'action' => $command], $params);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Internal-Secret' => config('scooter.internal_secret'),
                    'Accept'            => 'application/json',
                ])
                ->post("{$this->baseUrl}/scooter/command", $body);
        } catch (ConnectionException $e) {
            Log::error('ScooterService: cannot reach Python server', [
                'imei'    => $imei,
                'command' => $command,
                'error'   => $e->getMessage(),
            ]);

            throw new ScooterException(
                'Cannot reach the scooter server. Is the Python TCP server running?',
                0,
                $e
            );
        }

        if ($response->failed()) {
            Log::error('ScooterService: Python server error', [
                'imei'    => $imei,
                'command' => $command,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            throw new ScooterException(
                "Scooter server responded with HTTP {$response->status()}: {$response->body()}"
            );
        }

        $json = $response->json();

        return new ScooterCommandResult(
            success: (bool)   ($json['success'] ?? false),
            message: (string) ($json['message'] ?? ''),
            data: (array)  ($json['data']    ?? []),
        );
    }
}
