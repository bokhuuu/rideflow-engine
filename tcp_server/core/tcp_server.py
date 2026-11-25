# core/tcp_server.py
# ─────────────────────────────────────────────────────────────────────────────
# Core TCP server - accepts scooter connections and dispatches incoming packets.
#
# Two responsibilities:
#   1. handle_client() - runs per-scooter in its own thread, reads packets,
#      dispatches to the correct device handler
#   2. start_tcp_server() - binds the socket, starts the accept loop
#
# This module is intentionally device-agnostic:
#   - It doesn't know what commands mean
#   - It doesn't build protocol packets
#   - It only reads raw lines, parses the envelope and routes by command code
#   - Device-specific logic lives entirely in devices/omni_m151/
# ─────────────────────────────────────────────────────────────────────────────

import socket
import threading

from core.connection_pool import connected_scooters, connections_lock
from core.packet_parser import parse_message
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


def handle_client(conn, addr):
    """
    Handle one scooter connection for its entire lifetime.

    Runs in its own thread - one thread per connected scooter.
    Reads incoming TCP data continuously, splits on newlines, parses each packet
    and dispatches to the correct handler function.

    Buffer pattern:
        TCP doesn't guarantee one send() = one recv(). A 100-byte packet might
        arrive as two 50-byte chunks. We accumulate chunks in `buffer` and only
        process complete lines (ending with \\n).

    Args:
        conn (socket.socket): The accepted TCP connection from this scooter.
        addr (tuple):         (ip, port) of the connecting scooter.
    """
    imei   = None   
    buffer = ""     

    print(f"\n[+] New connection from {addr}")

    try:
        while True:
            # Read up to 1024 bytes - may be a partial packet or multiple packets
            chunk = conn.recv(1024)

            if not chunk:
                break 

            # Append new bytes to buffer - decode as UTF-8, ignore bad bytes
            buffer += chunk.decode(errors='ignore')

            # Process every complete line in the buffer.
            # A complete packet ends with \n - split on that.
            # After split: line = the packet, buffer = whatever came after \n
            while '\n' in buffer:
                line, buffer = buffer.split('\n', 1) 
                line = line.strip()

                # Skip empty lines and non-protocol noise
                if not line or '*SCOR' not in line:
                    continue

                # Parse the raw line into a dict with imei/command/data
                msg = parse_message(line)
                if not msg:
                    continue 

                # First message tells us the IMEI - register in connection pool
                if imei is None:
                    imei = msg["imei"]
                    with connections_lock:
                        connected_scooters[imei] = conn
                    print(f"[+] Scooter registered: IMEI={imei}")

                print(f"  RAW: {line}")

                # Dispatch to handler based on command code
                cmd = msg["command"]
                if   cmd == "Q0": handle_Q0(conn, msg)
                elif cmd == "H0": handle_H0(conn, msg)
                elif cmd == "R0": handle_R0(conn, msg, imei)
                elif cmd == "L0": handle_L0(conn, msg, imei)
                elif cmd == "L1": handle_L1(conn, msg, imei)
                elif cmd == "D0": handle_D0(conn, msg)
                elif cmd == "S6": handle_S6(conn, msg)
                elif cmd == "W0": handle_W0(conn, msg, imei)
                else:
                    print(f"  [{cmd}] unhandled | data={msg['data']}")

    except Exception as e:
        print(f"[-] Error with {addr}: {e}")

    finally:
        # Always clean up - even if an exception occurred
        # Remove from registry so no one tries to send to a dead socket
        if imei:
            with connections_lock:
                connected_scooters.pop(imei, None)
        conn.close()
        print(f"[-] Disconnected: IMEI={imei} | addr={addr}")


def start_tcp_server(port):
    """
    Bind TCP socket on the given port and start the accept loop in a thread.

    Uses SO_REUSEADDR so the port is immediately available after restart -
    without it, the OS holds the port for ~60 seconds after the process exits.

    The accept loop spawns one daemon thread per scooter connection.
    Daemon threads die automatically when the main process exits.

    Args:
        port (int): TCP port to listen on (default 9000 from env).

    Returns:
        socket.socket: The bound server socket (caller can close it to stop).
    """
    tcp_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

    # SO_REUSEADDR: allow reuse of the port immediately after restart
    tcp_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)

    # Bind to all interfaces (0.0.0.0) so Docker port mapping works
    tcp_socket.bind(("0.0.0.0", port))

    # Queue up to 100 pending connections before refusing new ones
    tcp_socket.listen(100)

    print(f"TCP server listening on port {port} (scooters)...")

    def accept_loop():
        """
        Wait for new connections in a loop.
        Each accepted connection gets its own thread running handle_client().
        """
        while True:
            try:
                conn, addr = tcp_socket.accept()
                threading.Thread(
                    target=handle_client,
                    args=(conn, addr),
                    daemon=True, 
                ).start()
            except OSError:
                # tcp_socket.close() raises OSError in accept() - that's our stop signal
                break

    threading.Thread(target=accept_loop, daemon=True).start()

    return tcp_socket