
import sys
import os
import pytest

# ── Make server.py importable ─────────────────────────────────────────────────
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))


# ── Fake socket ───────────────────────────────────────────────────────────────
class FakeSocket:
    """
    A fake TCP socket for testing.

    Real sockets send bytes over a network - we can't use those in unit tests
    because there's no server running. This fake socket:
      - Records every sendall() call in self.sent (so we can assert what was sent)
      - Never actually sends anything over the network
      - Mimics the interface of a real socket so server.py code doesn't know

    Usage in tests:
        sock = FakeSocket()
        handle_W0(sock, msg, "123456789123456")
        assert b"W0" in sock.sent[0]   # check the right command was sent
    """

    def __init__(self):
        """Initialize with an empty list to record all sent data."""
        self.sent = []

    def sendall(self, data: bytes):
        """
        Record the data instead of sending it over a network.
        `data` is always bytes - server.py always calls sendall() with bytes.
        """
        self.sent.append(data)


@pytest.fixture
def fake_socket():
    """
    Pytest fixture that gives each test a fresh FakeSocket.

    How fixtures work:
      - pytest sees a test function with parameter named `fake_socket`
      - pytest calls this fixture function and passes the result as that parameter
      - Each test gets its own fresh FakeSocket (self.sent starts empty every time)

    Example test:
        def test_something(fake_socket):
            handle_W0(fake_socket, msg, imei)
            assert len(fake_socket.sent) == 1
    """
    return FakeSocket()


@pytest.fixture
def sample_imei():
    """
    Standard test IMEI used across all tests.
    Real IMEIs are 15 digits - this matches the simulator's IMEI.
    Using one consistent value makes test output easier to read.
    """
    return "123456789123456"


@pytest.fixture(autouse=True)
def clean_state():
    """
    Reset global server state before every test.

    autouse=True means this fixture runs automatically for every test
    - you don't need to add it as a parameter.

    Why this matters:
      server.py stores connected scooters in a global dict.
      If test_A registers a scooter and test_B runs after it,
      test_B would see test_A's scooter - tests would interfere with each other.
      This fixture wipes the globals clean before each test.
    """
    
    import server

    server.connected_scooters.clear()        
    server.pending_geofence_locks.clear()   
    server.pending_lock_results.clear()

    yield

    server.connected_scooters.clear()
    server.pending_geofence_locks.clear()
    server.pending_lock_results.clear()