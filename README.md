# RideFlow 🛴

Electric scooter sharing platform for the Georgian market.

## Stack

- Laravel 13 + Filament 5 — API, admin panel, web QR flow
- Python TCP server — persistent IoT device communication
- MySQL — primary database
- Flitt (TBC Group) — payment processing
- OMNI M151 — IoT scooter hardware
- Leaflet.js — maps and geofencing
- Docker — containerized deployment
- Nginx + Let's Encrypt — reverse proxy with SSL

## Architecture

```
Rider scans QR → Laravel → Flitt payment → Python TCP → OMNI M151 → Scooter unlocks
```

Python maintains persistent TCP connections to scooters.
Laravel communicates with Python via internal HTTP bridge on port 8080.
Riders are anonymous — no accounts, no registration. Scan, pay, ride.

## Features

### Ride flow

- QR scan → package selection → Flitt payment → scooter unlock → timed ride → auto-lock
- 5 fixed packages (5/10/20/30/60 min) + unlock fee — all DB-driven from admin panel
- Anonymous riders — identity tied to payment session only
- 4 languages: EN / KA / RU / HY

### IoT / TCP

- Raw TCP server — OMNI M151 protocol (Q0, H0, R0, L0, L1, D0, D1, S4, V0, W0)
- Full unlock handshake: R0 request → KEY exchange → L0 confirm
- Real-time GPS tracking (D1) during rides
- Heartbeat monitoring (H0) — battery % updated every 4 minutes
- Pluggable device architecture: `core/` + `devices/omni_m151/` — add new devices without touching core

### Geofencing

- Server-side ray casting polygon algorithm
- Violation sequence: beep → speed cap (6km/h) → grace period → lock → admin email
- Event-driven: `ScooterLeftZoneEvent` / `ScooterReturnedToZoneEvent`
- Multi-zone support — admin draws polygons via Leaflet.draw

### Payments

- Flitt direct charge — upfront payment before unlock
- Signature verification on every callback
- No partial refunds — fixed price, fixed time

### Admin panel (Filament 5)

- Scooter management — status, battery, live GPS location
- Ride and payment records — view only
- Zone editor — interactive polygon drawing on Leaflet map
- DB-driven settings — unlock fee, timeout buffer, support contacts

### Infrastructure

- Docker Compose — single command brings up all 7 services
- Separate dev/prod nginx configs — SSL only in production
- Queue worker + scheduler — geofence grace period, auto-lock
- Gmail SMTP — geofence lock notifications to admin

### Tests

- 80+ Pest tests — payment flow, geofencing, ride lifecycle, API endpoints
- 62 pytest tests — TCP parser, commands, protocol handlers
- GitHub Actions CI — runs all tests on every push to main

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
