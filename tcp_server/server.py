# server.py
# ─────────────────────────────────────────────────────────────────────────────
# Python TCP server for RideFlow.
#
# Two jobs in one process:
#   1. TCP server (port 9000) - keeps persistent connections with OMNI scooters
#   2. HTTP server (port 8080) - receives commands from Laravel
#
# Flow:
#   Scooter connects → TCP → this server → forwards GPS to Laravel → HTTP
#   Laravel detects violation → HTTP → this server → sends commands → TCP → Scooter
# ─────────────────────────────────────────────────────────────────────────────

import threading
import os
import requests

# ── Configuration from environment ───────────────────────────────────────────
# All values read from environment variables so Docker/production can override.
# Defaults are for local development.
LARAVEL_URL = os.environ.get("LARAVEL_URL", "http://127.0.0.1:8000")
INTERNAL_SECRET = os.environ.get("TCP_SERVER_SECRET", "rideflow-internal-secret")
TCP_PORT = int(os.environ.get("TCP_PORT", 9000))
HTTP_PORT = int(os.environ.get("HTTP_PORT", 8080))

# ── Connection registry ───────────────────────────────────────────────────────
from core.connection_pool import (
    connected_scooters,
    connections_lock,
    pending_geofence_locks,
    pending_lock_results,
    pending_lock_results_lock,
)


# ── Message builder ───────────────────────────────────────────────────────────
from devices.omni_m151.commands import build_command 


# ── Message parser ────────────────────────────────────────────────────────────
from core.packet_parser import parse_message


# ── Command handlers ──────────────────────────────────────────────────────────
from devices.omni_m151.parser import (
    handle_Q0,
    handle_H0,
    handle_R0,
    handle_L0,
    handle_L1,
    handle_D0,
    handle_S6,
    handle_W0,
)


# ── Per-scooter connection thread ─────────────────────────────────────────────
from core.tcp_server import handle_client  # noqa: F401


# ── Laravel-triggered actions ─────────────────────────────────────────────────
from core.command_sender import ( 
    unlock_scooter,
    lock_scooter,
    beep_scooter,
    get_connected_scooters,
    execute_geofence_sequence,
)


# ── HTTP server (Flask) ───────────────────────────────────────────────────────
# Laravel cannot talk to scooters directly - it doesn't hold TCP connections.
# This small HTTP server is the bridge: Laravel calls here, we forward to scooter.
from flask import Flask, request, jsonify

http_app = Flask(__name__)


@http_app.route("/geofence-violation", methods=["POST"])
def geofence_violation():
    """
    Called by Laravel when a scooter crosses the zone boundary.
    Expects: { "imei": "123456789123456", "ride_id": 42 }
    Starts the geofence sequence in a background thread.
    """
    body = request.json

    if not body or "imei" not in body:
        return jsonify({"success": False, "message": "Missing imei"}), 400

    imei    = body["imei"]
    ride_id = body.get("ride_id", 0)

    with connections_lock:
        conn = connected_scooters.get(imei)

    if not conn:
        return jsonify({"success": False, "message": "Device not connected"}), 503

    threading.Thread(
        target=execute_geofence_sequence,
        args=(imei, conn, ride_id),
        daemon=True
    ).start()

    return jsonify({"success": True, "message": "Geofence sequence initiated"})


@http_app.route("/scooter/command", methods=["POST"])
def scooter_command():
    """
    General command endpoint for Laravel to trigger scooter actions.
    Expects: { "imei": "...", "action": "unlock|lock|beep|status", "user_id": 42 }
    """
    body = request.json

    if not body or "imei" not in body or "action" not in body:
        return jsonify({"success": False, "message": "Missing imei or action"}), 400

    imei = body["imei"]
    action = body["action"]
 
    if action == "unlock":
        success, message = unlock_scooter(imei, body.get("user_id", 0))

    elif action == "lock":
        user_id = body.get("user_id", 0)
        success, message, duration = lock_scooter(imei, user_id)
        status_code = 200 if success else 503
        return jsonify({
            "success": success,
            "message": message,
            "data": {
                "ride_duration_minutes": duration,
            },
        }), status_code

    elif action == "beep":
        success, message = beep_scooter(imei)

    elif action == "tracking_start":
        # D1 command - scooter sends GPS every 10 seconds automatically
        with connections_lock:
            conn = connected_scooters.get(imei)
        if not conn:
            return jsonify({"success": False, "message": f"Scooter {imei} not connected"}), 503
        conn.sendall(build_command(imei, "D1", "10"))
        print(f"[>] D1 tracking started | IMEI={imei}")
        success, message = True, "Tracking started"

    elif action == "tracking_stop":
        # D1,0 - interval=0 means stop tracking
        with connections_lock:
            conn = connected_scooters.get(imei)
        if not conn:
            return jsonify({"success": False, "message": f"Scooter {imei} not connected"}), 503
        conn.sendall(build_command(imei, "D1", "0"))
        print(f"[>] D1 tracking stopped | IMEI={imei}")
        success, message = True, "Tracking stopped"

    elif action == "slow_down":
        # S4 - cap all speed modes to 6 km/h (geofence warning)
        with connections_lock:
            conn = connected_scooters.get(imei)
        if not conn:
            return jsonify({"success": False, "message": f"Scooter {imei} not connected"}), 503
        conn.sendall(build_command(imei, "S4", "0", "0", "0", "0", "0", "6", "6", "6"))
        print(f"[>] S4 slow down sent | IMEI={imei}")
        success, message = True, "Speed capped at 6km/h"

    elif action == "restore_speed":
        # S4 - restore normal speed limits: low=15, medium=20, high=25 km/h
        with connections_lock:
            conn = connected_scooters.get(imei)
        if not conn:
            return jsonify({"success": False, "message": f"Scooter {imei} not connected"}), 503
        conn.sendall(build_command(imei, "S4", "0", "0", "0", "0", "0", "15", "20", "25"))
        print(f"[>] S4 speed restored | IMEI={imei}")
        success, message = True, "Speed limits restored"    

    elif action == "status":
        return jsonify({"success": True, "connected": get_connected_scooters()})

    else:
        return jsonify({"success": False, "message": f"Unknown action: {action}"}), 400

    status_code = 200 if success else 503
    return jsonify({"success": success, "message": message}), status_code


# ── Entry point ───────────────────────────────────────────────────────────────
if __name__ == "__main__":
    from core.tcp_server import start_tcp_server
    start_tcp_server(TCP_PORT)

    print("HTTP server listening on port 8080 (Laravel)...")
    print("Ready.\n")
    http_app.run(host='0.0.0.0', port=HTTP_PORT, debug=False)