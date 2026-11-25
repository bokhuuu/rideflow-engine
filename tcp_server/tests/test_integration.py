# ─────────────────────────────────────────────────────────────────────────────
# Integration tests - tests the full message flow over a real TCP connection.
#
# Integration tests spin up a REAL TCP server on a random port and connect
# to it with a real socket. This tests the full path:
#
#   Client socket → TCP server → handle_client() → parse_message() → handler
#
# Random port?
#   Port 9000 may already be in use (by another test run, by Docker, by the
#   real server). Using port 0 tells the OS to pick any free port.
#
# Testing:
#   - Q0 check-in registers the scooter in connected_scooters
#   - H0 heartbeat doesn't crash the server
#   - Full unlock handshake: R0 → server sends L0
#   - Full lock handshake: R0 → server sends L1
#   - Disconnection cleans up connected_scooters
# ─────────────────────────────────────────────────────────────────────────────

import socket
import threading
import time
import pytest
import server


# ── Helpers ───────────────────────────────────────────────────────────────────

def start_test_tcp_server():
    """
    Spin up the TCP server on a random free port for testing.

    Returns (server_socket, port) so tests can connect to it.
    The server_socket stays open for the test's duration.

    We replicate the accept loop from server.py's __main__ block,
    but on a random port so we don't conflict with the real server.
    """
    tcp_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

    tcp_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)

    tcp_socket.bind(("127.0.0.1", 0))

    port = tcp_socket.getsockname()[1]

    tcp_socket.listen(100)

    def accept_loop():
        """
        Accept connections in a background thread.
        daemon=True means this thread dies when the test process ends.
        """
        while True:
            try:
                conn, addr = tcp_socket.accept()
                threading.Thread(
                    target=server.handle_client,
                    args=(conn, addr),
                    daemon=True
                ).start()
            except OSError:
                break

    threading.Thread(target=accept_loop, daemon=True).start()

    return tcp_socket, port


def scooter_send(sock, imei, message):
    """
    Send a message from a fake scooter to the server.
    Uses *SCOR prefix (IoT→server direction) with no 0xFFFF prefix.
    """
    full = f"*SCOR,OM,{imei},{message}#\n"
    sock.sendall(full.encode())


def scooter_recv(sock, timeout=2.0):
    """
    Receive a response from the server.
    Strips the 0xFFFF prefix and decodes to string.
    Returns empty string if nothing arrives within timeout.
    """
    sock.settimeout(timeout)
    try:
        data = sock.recv(1024)
        if data:
            return data[2:].decode(errors="ignore")
    except socket.timeout:
        pass
    return ""


# ── Fixture ───────────────────────────────────────────────────────────────────

@pytest.fixture
def tcp_server():
    """
    Start a test TCP server and yield (server_socket, port).
    Tears down (closes socket) after the test.

    Each test gets its own server on its own port - no interference.
    """
    srv_socket, port = start_test_tcp_server()

    yield srv_socket, port

    srv_socket.close()


@pytest.fixture
def connected_client(tcp_server, sample_imei, mocker):
    """
    A client socket already connected to the test server,
    with Q0 check-in already sent (scooter registered).

    Most integration tests need a connected+registered scooter.
    This fixture sets it up so tests can skip the connection boilerplate.

    mocker.patch stops handle_H0 from making real HTTP calls.
    """
    mocker.patch("server.requests.post")

    srv_socket, port = tcp_server

    client = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    client.connect(("127.0.0.1", port))

    scooter_send(client, sample_imei, "Q0,412,80,28")

    time.sleep(0.2)

    yield client 

    client.close()


# ── Tests ─────────────────────────────────────────────────────────────────────

class TestConnection:
    """Tests about connecting and disconnecting."""

    def test_q0_registers_scooter(self, connected_client, sample_imei):
        """
        After Q0, the scooter's IMEI must appear in connected_scooters.
        This is how the server knows which socket belongs to which scooter.
        """
        assert sample_imei in server.connected_scooters

    def test_disconnect_removes_scooter(self, connected_client, sample_imei):
        """
        When the scooter disconnects, it must be removed from connected_scooters.
        Otherwise lock_scooter() would try to send to a dead socket.
        """
        assert sample_imei in server.connected_scooters 

        connected_client.close()

        time.sleep(0.3)

        assert sample_imei not in server.connected_scooters

    def test_multiple_scooters_can_connect(self, tcp_server, mocker):
        """
        Multiple scooters connect simultaneously - each gets its own thread.
        All must be registered with their own IMEI.
        """
        mocker.patch("server.requests.post")
        srv_socket, port = tcp_server

        imeis = ["111111111111111", "222222222222222", "333333333333333"]
        clients = []

        for imei in imeis:
            c = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            c.connect(("127.0.0.1", port))
            scooter_send(c, imei, "Q0,412,80,28")
            clients.append(c)

        time.sleep(0.3)

        for imei in imeis:
            assert imei in server.connected_scooters

        for c in clients:
            c.close()


class TestHeartbeat:
    """H0 heartbeat integration."""

    def test_h0_does_not_crash_server(self, connected_client, sample_imei, mocker):
        """
        Heartbeat arrives - server must stay up and scooter must stay registered.
        """
        mocker.patch("server.requests.post")

        scooter_send(connected_client, sample_imei, "H0,1,412,28,80,0")
        time.sleep(0.2)

        assert sample_imei in server.connected_scooters


class TestUnlockFlow:
    """
    Full unlock handshake over real TCP.

    Flow:
      1. Server calls unlock_scooter() → sends R0
      2. Scooter (our client socket) receives R0, replies with R0+KEY
      3. Server receives R0 reply → sends L0 with KEY
      4. We receive L0 and verify KEY is correct
    """

    def test_full_unlock_handshake(self, connected_client, sample_imei):
        """
        The unlock handshake must complete end-to-end:
        unlock_scooter() → R0 sent → client replies R0 → server sends L0.
        """
        success, message = server.unlock_scooter(sample_imei, user_id=99)
        assert success is True

        r0_raw = scooter_recv(connected_client)
        assert "R0" in r0_raw, f"Expected R0, got: {r0_raw!r}"

        parts = r0_raw.strip().rstrip("#").split(",")
        operation = parts[4]  
        user_id   = parts[6]
        timestamp = parts[7]

        assert operation == "0"  

        key = "42" 
        scooter_send(connected_client, sample_imei, f"R0,{operation},{key},{user_id},{timestamp}")

        l0_raw = scooter_recv(connected_client)
        assert "L0" in l0_raw, f"Expected L0, got: {l0_raw!r}"
        assert key in l0_raw  


class TestLockFlow:
    """Full lock handshake over real TCP."""

    def test_full_lock_handshake(self, connected_client, sample_imei):
        """
        Lock flow:
          lock_scooter() → R0(lock) sent → client replies R0+KEY → server sends L1
          → client sends L1 result → lock_scooter() returns with duration
        """
        result_container = {}

        def run_lock():
            """Run lock_scooter() in a thread - it blocks waiting for L1."""
            success, message, duration = server.lock_scooter(sample_imei, user_id=99)
            result_container["success"]  = success
            result_container["duration"] = duration

        lock_thread = threading.Thread(target=run_lock, daemon=True)
        lock_thread.start()

        r0_raw = scooter_recv(connected_client, timeout=3.0)
        assert "R0" in r0_raw, f"Expected R0, got: {r0_raw!r}"

        parts     = r0_raw.strip().rstrip("#").split(",")
        operation = parts[4]  
        user_id   = parts[6]
        timestamp = parts[7]

        assert operation == "1"

        key = "77"
        scooter_send(connected_client, sample_imei, f"R0,{operation},{key},{user_id},{timestamp}")

        l1_raw = scooter_recv(connected_client, timeout=3.0)
        assert "L1" in l1_raw, f"Expected L1, got: {l1_raw!r}"

        scooter_send(connected_client, sample_imei, f"L1,0,{user_id},{timestamp},5")

        lock_thread.join(timeout=5.0)

        assert result_container.get("success") is True
        assert result_container.get("duration") == 5