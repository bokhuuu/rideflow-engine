# core/connection_pool.py
# ─────────────────────────────────────────────────────────────────────────────
# Shared state for tracking connected scooter sockets.
#
# Multiple modules need access to connected_scooters:
#     - tcp_server.py    → registers/removes scooters on connect/disconnect
#     - command_sender.py → looks up sockets to send commands
#     - omni_m151/parser.py → clears geofence flags after lock
#
#   If each module defined its own dict, they'd never see each other's entries.
#   One shared module = one source of truth.
#
# Thread safety:
#   connections_lock protects connected_scooters.
#   pending_lock_results_lock protects pending_lock_results.
#   Both are threading.Lock() - only one thread can hold a lock at a time.
#   Without locks, two threads writing simultaneously could corrupt the dict.
# ─────────────────────────────────────────────────────────────────────────────

import threading

# ── Active scooter connections ────────────────────────────────────────────────
# Key   = IMEI (str)         - 15-digit unique scooter identifier
# Value = socket connection  - open TCP socket to that scooter
#
# To send a command to a scooter, we look up its socket here.
connected_scooters = {}

# Protects connected_scooters from concurrent writes.
# Lock ensures only one thread modifies the dict at a time.
connections_lock = threading.Lock()

# ── Geofence lock tracking ────────────────────────────────────────────────────
# Tracks scooters that have a geofence-triggered lock in progress.
# Set to True before sending R0, cleared in handle_R0 after L1 is sent.
# Key = IMEI, Value = True
pending_geofence_locks = {}

# ── Lock operation results ────────────────────────────────────────────────────
# lock_scooter() creates an entry here before sending R0, then blocks on event.wait().
# handle_L1() writes the result and calls event.set() to unblock lock_scooter().
#
# Entry structure:
#   {
#     "event":    threading.Event  - lock_scooter() waits on this
#     "duration": int              - handle_L1() writes ride duration here
#     "success":  bool             - handle_L1() writes True/False here
#   }
pending_lock_results = {}

# Protects pending_lock_results from concurrent writes.
# lock_scooter() and handle_L1() can run in different threads simultaneously.
pending_lock_results_lock = threading.Lock()