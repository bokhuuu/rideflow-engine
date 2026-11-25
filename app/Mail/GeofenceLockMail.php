<?php

namespace App\Mail;

use App\Models\Ride;
use App\Models\Scooter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to admin when a scooter is locked after grace period expires.
 * Rider left the zone and did not return - scooter has been locked automatically.
 * Contains scooter details, coordinates and Google Maps link.
 */
class GeofenceLockMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Ride $ride,
        public readonly Scooter $scooter,
        public readonly float $lat,
        public readonly float $lng,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🔒 Scooter {$this->scooter->imei} locked - rider left the zone",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.geofence-lock',
        );
    }
}
