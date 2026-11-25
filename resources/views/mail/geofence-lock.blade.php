<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: sans-serif;
            color: #1a1a1a;
            padding: 24px;
        }

        .badge {
            background: #ef4444;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        td:first-child {
            color: #6b7280;
            width: 160px;
        }

        .map-link {
            display: inline-block;
            margin-top: 20px;
            background: #2563eb;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <span class="badge">🔒 SCOOTER LOCKED</span>

    <h2 style="margin-top: 16px;">Scooter locked — rider left the operating zone</h2>

    <p style="color: #6b7280;">
        The rider left the operating zone and did not return within the grace period.
        The scooter has been automatically locked. Staff action may be required.
    </p>

    <table>
        <tr>
            <td>Scooter IMEI</td>
            <td><strong>{{ $scooter->imei }}</strong></td>
        </tr>
        <tr>
            <td>Scooter Name</td>
            <td>{{ $scooter->name }}</td>
        </tr>
        <tr>
            <td>Ride ID</td>
            <td>#{{ $ride->id }}</td>
        </tr>
        <tr>
            <td>Violated At</td>
            <td>{{ $ride->zone_violated_at }}</td>
        </tr>
        <tr>
            <td>Last Coordinates</td>
            <td>{{ $lat }}, {{ $lng }}</td>
        </tr>
        <tr>
            <td>Ride Started</td>
            <td>{{ $ride->started_at }}</td>
        </tr>
    </table>

    <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" class="map-link">
        📍 View on Google Maps
    </a>

    <p style="margin-top: 32px; color: #6b7280; font-size: 13px;">
        SGScooter — Automated geofence alert. Please recover the scooter if needed.
    </p>

</body>

</html>
