# core/command_sender.py
# ─────────────────────────────────────────────────────────────────────────────
# Actions triggered by Laravel via HTTP → forwarded to scooters via TCP.
#
# These functions are called by the Flask HTTP server when Laravel sends a
# command to /scooter/command. They look up the scooter's socket in the
# connection pool and send the appropriate protocol command.
#
# All functions are device-agnostic at this layer - they use build_command()
# which is injected via the module-level import. If a future device uses a
# different command format, only the devices/ layer changes.
# ─────────────────────────────────────────────────────────────────────────────

import threading
import time

from core.connection_pool import (
    connected_scooters,
    connections_lock,
    pending_geofence_locks,
    pending_lock_results,
    pending_lock_results_lock,
)
from devices.omni_m151.commands import build_command


def unlock_scooter(imei, user_id):
    """
    Start the unlock handshake: send R0 → scooter replies with KEY → handle_R0 sends L0.

    Only sends the R0 trigger. The rest of the handshake happens
    asynchronously in handle_R0() when the scooter's reply arrives.

    Args:
        imei    (str): Scooter to unlock.
        user_id (int): Rider's session ID - echoed through the handshake for audit.

    Returns:
        tuple[bool, str]: (True, message) if R0 sent, (False, message) if offline.
    """
    # Look up this scooter's socket - need the lock to safely read the shared dict
    with connections_lock:
        conn = connected_scooters.get(imei)

    # If scooter isn't in the registry, it's not connected - can't send anything
    if not conn:
        return False, f"Scooter {imei} not connected"

    # Send R0 with operation=0 (unlock), validity=20s, user_id, current timestamp
    conn.sendall(build_command(imei, "R0", "0", "20", user_id, int(time.time())))
    print(f"[>] R0 unlock request sent | IMEI={imei} | user={user_id}")
    return True, "Unlock command sent"


def lock_scooter(imei, user_id):
    """
    Start the lock handshake and wait for the L1 result to get ride duration.

    This function BLOCKS until handle_L1() signals completion (or 15s timeout).
    It's called from the HTTP handler which runs in its own thread, so blocking
    here is safe - it doesn't block the TCP server or other scooters.

    Flow:
      1. Create threading.Event and store in pending_lock_results
      2. Send R0 (operation=1 = lock)
      3. handle_R0() receives KEY → sends L1
      4. handle_L1() receives lock result → writes duration + sets event
      5. event.wait() unblocks → we read the result and return

    Args:
        imei    (str): Scooter to lock.
        user_id (int): Rider's session ID.

    Returns:
        tuple[bool, str, int]: (success, message, ride_duration_minutes)
    """
    with connections_lock:
        conn = connected_scooters.get(imei)

    if not conn:
        return False, f"Scooter {imei} not connected", 0

    # threading.Event is a simple flag - starts False, set() makes it True.
    # wait() blocks the current thread until set() is called or timeout expires.
    event = threading.Event()

    # Register this lock operation so handle_L1() can find it by IMEI
    with pending_lock_results_lock:
        pending_lock_results[imei] = {
            "event":    event,
            "duration": 0,    
            "success":  False, 
        }

    # Send R0 with operation=1 (lock) - rest of handshake is asynchronous
    conn.sendall(build_command(imei, "R0", "1", "20", user_id, int(time.time())))
    print(f"[>] R0 lock request sent | IMEI={imei} | user={user_id}")

    # Block here until handle_L1() calls event.set(), or 15 seconds pass
    # Returns True if event was set, False if timeout expired
    got_response = event.wait(timeout=15)

    # Read the result that handle_L1() wrote, then remove the entry
    with pending_lock_results_lock:
        result = pending_lock_results.pop(imei, {})

    if not got_response:
        print(f"[>] Lock timeout for {imei} - no L1 response in 15s")
        return True, "Lock command sent (timeout waiting for confirmation)", 0

    duration = result.get("duration", 0)
    success  = result.get("success", False)

    print(f"[>] Lock confirmed | IMEI={imei} | duration={duration}min")
    return success, "Lock confirmed", duration


def beep_scooter(imei):
    """
    Send V0 beep command - makes the scooter emit a sound so rider can find it.

    V0 param 2 = find-vehicle alert (different tone from geofence warning V0,1).

    Args:
        imei (str): Scooter to beep.

    Returns:
        tuple[bool, str]: (True, message) if sent, (False, message) if offline.
    """
    with connections_lock:
        conn = connected_scooters.get(imei)

    if not conn:
        return False, f"Scooter {imei} not connected"

    conn.sendall(build_command(imei, "V0", "2"))
    print(f"[>] Beep sent | IMEI={imei}")
    return True, "Beep command sent"


def get_connected_scooters():
    """
    Return list of IMEIs for all currently connected scooters.

    Used by the /scooter/command?action=status endpoint to tell Laravel
    which scooters are online right now.

    Returns:
        list[str]: List of IMEI strings.
    """
    with connections_lock:
        return list(connected_scooters.keys())


def execute_geofence_sequence(imei, conn, ride_id):
    """
    Full enforcement sequence when a scooter crosses the zone boundary.

    Designed to run in a background thread - HTTP response returns immediately
    while this plays out over several seconds.

    Sequence:
      1. V0,1  → out-of-range alert beep
      2. S4    → cap all speed modes to 6 km/h
      3. wait 3 seconds (rider feels the speed cap)
      4. R0,1  → request lock key → handle_R0 sends L1 → scooter locks

    Args:
        imei    (str):    Scooter IMEI.
        conn    (socket): Scooter's TCP connection.
        ride_id (int):    Active ride ID for logging.
    """
    try:
        print(f"[GEOFENCE] Sequence starting | IMEI={imei} | ride={ride_id}")

        # Step 1: Beep - V0,1 = out-of-range alert (different from find-vehicle V0,2)
        conn.sendall(build_command(imei, "V0", "1"))
        print(f"[GEOFENCE] V0 beep sent")
        time.sleep(0.5)

        # Step 2: Slow - S4 params: speed_meter, cruise, startup, btn, headlight, low, med, high
        # 0 = leave that setting unchanged; 6 = cap that speed mode at 6 km/h
        conn.sendall(build_command(imei, "S4", "0", "0", "0", "0", "0", "6", "6", "6"))
        print(f"[GEOFENCE] S4 slow sent - all speeds capped at 6km/h")

        # Step 3: Wait - rider perceives the speed drop before the lock
        print(f"[GEOFENCE] Waiting 3 seconds before lock...")
        time.sleep(3)

        # Step 4: Lock - mark as geofence lock so handle_R0 knows to send L1
        with connections_lock:
            pending_geofence_locks[imei] = True

        conn.sendall(build_command(imei, "R0", "1", "30", "0", int(time.time())))
        print(f"[GEOFENCE] R0 lock request sent")

    except Exception as e:
        print(f"[GEOFENCE] Error | IMEI={imei} | {e}")