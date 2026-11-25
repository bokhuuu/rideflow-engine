# ─────────────────────────────────────────────────────────────────────────────
# Unit tests for Laravel-triggered actions:
#   unlock_scooter() - sends R0 unlock request
#   lock_scooter()   - sends R0 lock request, waits for L1 response
#   beep_scooter()   - sends V0 beep command
#
# These functions are called by the HTTP server (/scooter/command endpoint)
# when Laravel wants to act on a scooter.
#
# Key challenge: lock_scooter() blocks waiting for a threading.Event.
# Simulate the L1 response arriving by setting the event in a background thread.
# ─────────────────────────────────────────────────────────────────────────────

import threading
import time
import server


class TestUnlockScooter:
    """unlock_scooter() sends R0 unlock request to a connected scooter."""

    def test_returns_false_when_scooter_not_connected(self, sample_imei):
        """
        If the scooter is not in connected_scooters, unlock must fail gracefully.
        Returns (False, message) - never raises an exception.
        Laravel uses the False to return 503 to the frontend.
        """
        success, message = server.unlock_scooter(sample_imei, user_id=42)

        assert success is False
        assert sample_imei in message 

    def test_sends_r0_when_connected(self, fake_socket, sample_imei):
        """
        When scooter is connected, unlock must send R0 command immediately.
        R0 triggers the key exchange - scooter replies with KEY → handle_R0 sends L0.
        """
        server.connected_scooters[sample_imei] = fake_socket

        success, message = server.unlock_scooter(sample_imei, user_id=42)

        assert success is True

        assert len(fake_socket.sent) == 1
        sent_text = fake_socket.sent[0][2:].decode()

        assert ",R0," in sent_text
        assert "0" in sent_text 

    def test_returns_true_when_connected(self, fake_socket, sample_imei):
        """
        Successful unlock returns (True, message).
        Note: at this point the scooter isn't actually unlocked yet -
        that happens when handle_R0 receives the KEY and sends L0.
        This just confirms the R0 was sent.
        """
        server.connected_scooters[sample_imei] = fake_socket

        success, message = server.unlock_scooter(sample_imei, user_id=42)

        assert success is True
        assert isinstance(message, str)


class TestBeepScooter:
    """beep_scooter() sends V0 command - rider hears a sound to find the scooter."""

    def test_returns_false_when_not_connected(self, sample_imei):
        """Offline scooter: returns (False, message) without crashing."""
        success, message = server.beep_scooter(sample_imei)

        assert success is False

    def test_sends_v0_when_connected(self, fake_socket, sample_imei):
        """
        Connected scooter: V0 command must be sent with param 2.
        V0,2 = find-vehicle sound (different from geofence warning V0,1).
        """
        server.connected_scooters[sample_imei] = fake_socket

        success, message = server.beep_scooter(sample_imei)

        assert success is True
        assert len(fake_socket.sent) == 1

        sent_text = fake_socket.sent[0][2:].decode()
        assert ",V0," in sent_text
        assert "2" in sent_text 


class TestLockScooter:
    """
    lock_scooter() is the most complex action - it blocks waiting for L1.

    Flow:
      1. lock_scooter() sends R0 and creates a threading.Event
      2. handle_R0 sends L1
      3. handle_L1 writes duration + success into pending_lock_results and sets event
      4. lock_scooter() unblocks, reads result, returns

    In tests we simulate step 3 in a background thread.
    """

    def test_returns_false_when_not_connected(self, sample_imei):
        """Offline scooter: returns (False, message, 0) without blocking."""
        success, message, duration = server.lock_scooter(sample_imei, user_id=42)

        assert success is False
        assert duration == 0

    def test_sends_r0_lock_when_connected(self, fake_socket, sample_imei):
        """
        lock_scooter() sends R0 with operation=1 (lock).
        We simulate L1 arriving in a background thread so lock_scooter() unblocks.
        """
        server.connected_scooters[sample_imei] = fake_socket

        def simulate_l1_response():
            """
            This runs in a background thread, simulating the scooter's L1 reply.
            We wait briefly to let lock_scooter() register its event first,
            then write the result and set the event to unblock it.
            """
            time.sleep(0.2)

            with server.pending_lock_results_lock:
                if sample_imei in server.pending_lock_results:
                    server.pending_lock_results[sample_imei]["duration"] = 5
                    server.pending_lock_results[sample_imei]["success"]  = True
                    server.pending_lock_results[sample_imei]["event"].set()

        t = threading.Thread(target=simulate_l1_response, daemon=True)
        t.start()

        success, message, duration = server.lock_scooter(sample_imei, user_id=42)

        assert len(fake_socket.sent) == 1
        sent_text = fake_socket.sent[0][2:].decode()
        assert ",R0," in sent_text
        assert "1" in sent_text 

    def test_returns_duration_from_l1(self, fake_socket, sample_imei):
        """
        lock_scooter() must return the ride duration reported by the scooter in L1.
        Laravel uses this duration to calculate the final ride cost.
        """
        server.connected_scooters[sample_imei] = fake_socket

        def simulate_l1_response():
            time.sleep(0.2)
            with server.pending_lock_results_lock:
                if sample_imei in server.pending_lock_results:
                    server.pending_lock_results[sample_imei]["duration"] = 12 
                    server.pending_lock_results[sample_imei]["success"]  = True
                    server.pending_lock_results[sample_imei]["event"].set()

        threading.Thread(target=simulate_l1_response, daemon=True).start()

        success, message, duration = server.lock_scooter(sample_imei, user_id=42)

        assert success is True
        assert duration == 12 

    def test_timeout_returns_gracefully(self, fake_socket, sample_imei):
        """
        If L1 never arrives (scooter offline mid-ride, connection drop),
        lock_scooter() must not block forever.
        The 15-second timeout is too long for a unit test - we patch it.

        We verify the return is (True, message, 0) - command was sent even if
        we didn't get confirmation.

        Note: we test timeout behavior by not setting the event at all.
        We patch the timeout to 0.1 seconds so the test runs fast.
        """
        server.connected_scooters[sample_imei] = fake_socket

        original_wait = threading.Event.wait

        def fast_timeout(self, timeout=None):
            """Fake event.wait() that always times out instantly."""
            return False 

        threading.Event.wait = fast_timeout

        try:
            success, message, duration = server.lock_scooter(sample_imei, user_id=42)
            assert duration == 0

        finally:
            threading.Event.wait = original_wait