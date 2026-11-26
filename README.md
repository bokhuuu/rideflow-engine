# RideFlow 🛴

Python maintains permanent TCP connections to scooters on port 9000.
Laravel is stateless — all cross-layer communication travels as HTTP to the Python service on port 8080.
Riders are anonymous — no accounts, no registration. Scan, pay, ride.

## What makes it technically interesting

- **No SDK existed.** Built entirely from raw protocol documentation.
- **Cryptographic 3-step unlock handshake:** R0 request → scooter replies with KEY → L0 confirm with KEY. Wrong KEY = no unlock.
- **Pluggable IoT architecture:** `core/` layer is completely device-agnostic. Adding a new hardware device = adding a new folder under `devices/`. Zero changes to core.
- **Ray casting geofencing** — server-side point-in-polygon algorithm, not a library.
- **Anonymous ride flow** — identity tied to payment session only. No user accounts anywhere in the system.

## Features

### Ride flow

- QR scan → package selection → Flitt payment → scooter unlock → timed ride → auto-lock
- 5 fixed packages (5/10/20/30/60 min) + unlock fee — all DB-driven from admin panel
- Anonymous riders — identity tied to payment session only
- 4 languages: EN / KA / RU / HY

### IoT / TCP

- Python asyncio TCP server — persistent connections, handles multiple scooters simultaneously
- Full OMNI M151 protocol: Q0 (check-in), H0 (heartbeat + battery), R0/KEY/L0 (unlock handshake), L1 (lock + ride duration), D1 (real-time GPS), S4 (speed cap), V0 (beep), W0 (alarm)
- Real-time GPS tracking (D1) during rides
- Heartbeat monitoring (H0) — battery % updated every 4 minutes
- Pluggable device architecture: `core/` + `devices/omni_m151/` — new device = new folder, no core changes

### Geofencing

- Server-side ray casting point-in-polygon algorithm
- Violation sequence: beep → speed cap (6 km/h) → grace period → lock → admin email
- Event-driven: `ScooterLeftZoneEvent` / `ScooterReturnedToZoneEvent`
- Multi-zone support — admin draws polygons via Leaflet.draw directly on the map

### Payments

- Flitt (TBC Group) direct charge — upfront payment before unlock
- SHA1 signature generation and verification on every callback
- QR scan → package selection → Flitt checkout → callback → unlock — one uninterrupted flow
- No partial refunds — fixed price, fixed time

### Admin panel (Filament 5)

- Scooter management — status, battery %, live GPS location
- Ride and payment records — view only
- Zone editor — interactive Leaflet.js polygon drawing on live map
- One-click beep and recover actions per scooter
- DB-driven settings — unlock fee, timeout buffer, support contacts

### Infrastructure

- Docker Compose — single command brings up all 7 services
- Separate dev/prod nginx configs — SSL only in production
- Queue worker + scheduler — geofence grace period, auto-lock
- Gmail SMTP — geofence lock notifications to admin

### Tests

- 80+ Pest tests — payment flow, geofencing, ride lifecycle, API endpoints
- 62 pytest tests — TCP parser, commands, protocol handlers
- GitHub Actions CI — runs both suites on every push to main

## Running locally

```bash
# Full stack
docker compose up

# Admin panel only
php artisan serve
# http://127.0.0.1:8000/admin

# TCP server + simulator
cd tcp_server
python3 server.py
python3 simulator.py
```
