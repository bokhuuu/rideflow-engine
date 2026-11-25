# simulator.py
# ─────────────────────────────────────────────────────────────────────────────
# Fake OMNI M151 scooter for testing without real hardware.
#
# Simulates exactly what a real scooter would do:
#   - Connects to the TCP server
#   - Sends check-in and heartbeats
#   - Responds to server commands (unlock, lock, beep, GPS request)
#
# Run this in a second terminal while server.py is running.
# When real hardware arrives, this file is no longer needed.
# ─────────────────────────────────────────────────────────────────────────────

import socket
import time
import random

# The IMEI must match the scooter record in the database
IMEI        = "123456789123456"
SERVER_IP   = "127.0.0.1"
SERVER_PORT = 9000


def send(sock, message):
    """
    Send a message FROM simulator TO server.
    Scooter→server messages use *SCOR prefix and NO 0xFFFF prefix.
    0xFFFF is only required on server→scooter direction.
    """
    full = f"*SCOR,OM,{IMEI},{message}#\n"
    print(f"  SIMULATOR SENDS: {full.strip()}")
    sock.sendall(full.encode())
    time.sleep(0.3)  # small delay between messages — real scooter does the same


def recv(sock):
    """
    Read a response from the server.
    Strips the 0xFFFF prefix (first 2 bytes) before returning the text.
    Returns empty string if nothing arrives within 2 seconds.
    """
    sock.settimeout(2.0)
    try:
        data = sock.recv(1024)
        if data:
            text = data[2:].decode(errors='ignore')  # [2:] skips the 0xFFFF prefix
            print(f"  SIMULATOR RECEIVES: {text.strip()}")
            return text
    except socket.timeout:
        pass
    return ""


# ── Connect to TCP server ─────────────────────────────────────────────────────
sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
sock.connect((SERVER_IP, SERVER_PORT))
print(f"Simulator connected to {SERVER_IP}:{SERVER_PORT}\n")

# Step 1: Check-in — real scooter always sends Q0 immediately after connecting
# Format: Q0,{voltage*100},{battery%},{signal}
send(sock, "Q0,412,80,28")
time.sleep(1)

# Step 2: Heartbeat — real scooter sends H0 periodically to stay registered
# Format: H0,{locked},{voltage*100},{signal},{battery%},{charging}
send(sock, "H0,1,412,28,80,0")
time.sleep(1)

print("\n--- Simulator ready. Waiting for server commands... ---\n")

# ── Main loop — listen for commands and respond like a real scooter ───────────
# key_storage saves the KEY we generated for R0 so we can verify it in L0/L1
key_storage = None

while True:
    response = recv(sock)

    if not response:
        # No command received — send heartbeat to stay alive
        send(sock, "H0,1,412,28,80,0")
        time.sleep(4)
        continue

    # Server can send multiple commands in one TCP chunk — process each line
    for line in response.strip().split('\n'):
        if '*SCOS' not in line:
            continue  # not a protocol command, skip

        parts = line.strip().rstrip('#').split(',')
        if len(parts) < 4:
            continue

        cmd = parts[3]  # command code is always at index 3

        if cmd == "R0":
            # Server requesting a lock/unlock key exchange
            # We generate a random KEY and send it back
            # Server will use this KEY in the following L0 or L1 command
            operation   = parts[4]  # 0=unlock, 1=lock
            user_id     = parts[6]
            timestamp   = parts[7]
            key_storage = random.randint(1, 255)  # random KEY, 0-255
            send(sock, f"R0,{operation},{key_storage},{user_id},{timestamp}")

        elif cmd == "L0":
            # Server sending actual unlock command with the KEY from R0
            if len(parts) > 4:
                received_key = parts[4]
                user_id      = parts[5]
                timestamp    = parts[6]
                if received_key == str(key_storage):
                    # KEY matches — unlock succeeds
                    send(sock, f"L0,0,{user_id},{timestamp}")  # 0 = success
                    print("  >>> SCOOTER UNLOCKED! <<<")
                else:
                    # KEY mismatch — reject with key error
                    send(sock, f"L0,2,{user_id},{timestamp}")  # 2 = key error
            # If len(parts) == 4, this is server's L0 acknowledgment — no reply needed

        elif cmd == "L1":
            # Server sending lock command with the KEY from R0
            if len(parts) > 4:
                # KEY check omitted here for simplicity — real scooter validates it
                send(sock, f"L1,0,1234,{int(time.time())},5")  # 0=success, 5=5min ride
                print("  >>> SCOOTER LOCKED! <<<")

        elif cmd == "V0":
            # Server triggered a beep — out-of-range alert or find-vehicle sound
            print("  >>> SCOOTER BEEPING! <<<")

        elif cmd == "S4":
            # Server changed speed limits — acknowledge silently
            # In geofence violation: all speeds are set to 6 km/h
            print("  >>> SPEED LIMITS UPDATED (geofence slow-down) <<<")

        elif cmd == "D0" or cmd == "D1":
            # Server requesting GPS location (D0=one-time, D1=start continuous tracking)
            # Send fake Ureki boulevard coordinates in DDMM.MMMM format:
            #   41.9969°N → 41 degrees + 59.814 minutes → "4159.8140" N
            #   41.7781°E → 41 degrees + 46.686 minutes → "04146.6860" E
            send(sock, "D0,1,120000.00,A,4159.4600,N,04145.9000,E,8,1.2,210526,4,M,A")