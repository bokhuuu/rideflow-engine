# ─────────────────────────────────────────────────────────────────────────────
# Unit tests for all handle_* functions in server.py.
#
# Each handler receives a parsed message dict and a socket.
# FakeSocket (from conftest.py) to capture what was sent.
# Mocker (from pytest-mock) to stub out HTTP calls to Laravel.
#
# Handlers tested:
#   handle_Q0  - check-in (no response expected)
#   handle_H0  - heartbeat (forwards battery to Laravel)
#   handle_R0  - key exchange (sends L0 or L1 depending on operation)
#   handle_L0  - unlock result (sends acknowledgment)
#   handle_L1  - lock result (sends acknowledgment, unblocks lock_scooter)
#   handle_D0  - GPS update (converts coordinates, forwards to Laravel)
#   handle_W0  - alarm event (sends acknowledgment)
# ─────────────────────────────────────────────────────────────────────────────

import threading
import server

class TestHandleQ0:
    """Q0 = check-in. Scooter sends this immediately after connecting."""

    def test_does_not_send_response(self, fake_socket, sample_imei):
        """
        Protocol: server does NOT respond to Q0.
        Scooter doesn't expect a response - sending one would confuse it.
        """
        msg = {
            "imei":    sample_imei,
            "command": "Q0",
            "data":    ["412", "80", "28"],
        }

        server.handle_Q0(fake_socket, msg)

        assert len(fake_socket.sent) == 0

    def test_handles_minimum_data(self, fake_socket, sample_imei):
        """
        handle_Q0 must not crash even if data is minimal.
        Defensive test - real scooters always send 3 fields, but we verify anyway.
        """
        msg = {
            "imei":    sample_imei,
            "command": "Q0",
            "data":    ["412", "80", "28"],
        }

        server.handle_Q0(fake_socket, msg)


class TestHandleH0:
    """H0 = heartbeat. Tests focus on Laravel forwarding."""

    def test_forwards_battery_to_laravel(self, fake_socket, sample_imei, mocker):
        """
        handle_H0 must POST battery % to Laravel's /api/internal/heartbeat.
        We mock requests.post so no real HTTP call is made.
        mocker.patch replaces the real function with a fake one for this test only.
        """
        mock_post = mocker.patch("server.requests.post")

        msg = {
            "imei":    sample_imei,
            "command": "H0",
            "data":    ["1", "412", "28", "75", "0"],
        }

        server.handle_H0(fake_socket, msg)

        mock_post.assert_called_once()

        call_kwargs = mock_post.call_args[1]
        assert call_kwargs["json"]["battery"] == 75
        assert call_kwargs["json"]["imei"] == sample_imei

    def test_does_not_crash_when_laravel_unreachable(self, fake_socket, sample_imei, mocker):
        """
        If Laravel is down, handle_H0 must NOT crash the TCP server.
        A heartbeat failure must never disconnect the scooter.
        We simulate this by making requests.post raise an exception.
        """
        mocker.patch("server.requests.post", side_effect=Exception("Connection refused"))

        msg = {
            "imei":    sample_imei,
            "command": "H0",
            "data":    ["1", "412", "28", "75", "0"],
        }

        server.handle_H0(fake_socket, msg)

    def test_sends_no_response_to_scooter(self, fake_socket, sample_imei, mocker):
        """
        H0 heartbeat: server does not reply to the scooter.
        Only forwards to Laravel. Scooter doesn't expect a TCP response.
        """
        mocker.patch("server.requests.post")

        msg = {
            "imei":    sample_imei,
            "command": "H0",
            "data":    ["1", "412", "28", "75", "0"],
        }

        server.handle_H0(fake_socket, msg)

        assert len(fake_socket.sent) == 0


class TestHandleR0:
    """R0 = key exchange response. Core of the unlock/lock handshake."""

    def test_unlock_sends_l0_with_key(self, fake_socket, sample_imei):
        """
        When operation=0 (unlock), handle_R0 must send L0 with the KEY.
        This is step 3 of the unlock handshake.
        KEY came from the scooter - we echo it back in L0.
        """
        msg = {
            "imei":    sample_imei,
            "command": "R0",
            "data":    ["0", "55", "1234", "9999"],
        }

        server.handle_R0(fake_socket, msg, sample_imei)

        assert len(fake_socket.sent) == 1

        sent_text = fake_socket.sent[0][2:].decode()

        assert ",L0," in sent_text

        assert "55" in sent_text

    def test_lock_sends_l1_with_key(self, fake_socket, sample_imei):
        """
        When operation=1 (lock), handle_R0 must send L1 with the KEY.
        Same handshake, different final command.
        """
        msg = {
            "imei":    sample_imei,
            "command": "R0",
            "data":    ["1", "99", "1234", "9999"], 
        }

        server.handle_R0(fake_socket, msg, sample_imei)

        assert len(fake_socket.sent) == 1
        sent_text = fake_socket.sent[0][2:].decode()

        assert ",L1," in sent_text
        assert "99" in sent_text

    def test_lock_clears_geofence_flag(self, fake_socket, sample_imei):
        """
        If this lock was triggered by a geofence violation,
        pending_geofence_locks[imei] was set to True before R0.
        handle_R0 must clear it after sending L1.
        """
        server.pending_geofence_locks[sample_imei] = True

        msg = {
            "imei":    sample_imei,
            "command": "R0",
            "data":    ["1", "99", "1234", "9999"],
        }

        server.handle_R0(fake_socket, msg, sample_imei)

        assert sample_imei not in server.pending_geofence_locks


class TestHandleL0:
    """L0 = unlock result from scooter."""

    def test_sends_acknowledgment(self, fake_socket, sample_imei):
        """
        Protocol requires server to send L0 acknowledgment after receiving L0.
        This is the "two-way verification" step.
        Without it, scooter may not confirm the unlock as complete.
        """
        msg = {
            "imei":    sample_imei,
            "command": "L0",
            "data":    ["0", "1234", "9999"], 
        }

        server.handle_L0(fake_socket, msg, sample_imei)

        assert len(fake_socket.sent) == 1
        sent_text = fake_socket.sent[0][2:].decode()

        assert f"*SCOS,OM,{sample_imei},L0#\n" == sent_text

    def test_sends_ack_even_on_failure(self, fake_socket, sample_imei):
        """
        Even if unlock failed (status=1), server must still send acknowledgment.
        Protocol requires acknowledgment regardless of result code.
        """
        msg = {
            "imei":    sample_imei,
            "command": "L0",
            "data":    ["1", "1234", "9999"],
        }

        server.handle_L0(fake_socket, msg, sample_imei)

        assert len(fake_socket.sent) == 1


class TestHandleL1:
    """L1 = lock result from scooter."""

    def test_sends_acknowledgment(self, fake_socket, sample_imei):
        """
        Same as L0 - server must acknowledge L1 receipt.
        Scooter expects this to confirm the lock sequence is complete.
        """
        msg = {
            "imei":    sample_imei,
            "command": "L1",
            "data":    ["0", "1234", "9999", "5"], 
        }

        server.handle_L1(fake_socket, msg, sample_imei)

        assert len(fake_socket.sent) == 1
        sent_text = fake_socket.sent[0][2:].decode()

        assert f"*SCOS,OM,{sample_imei},L1#\n" == sent_text

    def test_unblocks_waiting_lock_scooter(self, fake_socket, sample_imei):
        """
        lock_scooter() waits on a threading.Event for L1 to arrive.
        handle_L1 must write the result and call event.set() to unblock it.

        We simulate this by manually setting up pending_lock_results
        the same way lock_scooter() does, then check event is set after handle_L1.
        """
        event = threading.Event()

        server.pending_lock_results[sample_imei] = {
            "event":    event,
            "duration": 0,
            "success":  False,
        }

        msg = {
            "imei":    sample_imei,
            "command": "L1",
            "data":    ["0", "1234", "9999", "7"],
        }

        server.handle_L1(fake_socket, msg, sample_imei)

        assert event.is_set()

        assert server.pending_lock_results[sample_imei]["duration"] == 7

        assert server.pending_lock_results[sample_imei]["success"] is True

    def test_no_event_registered_does_not_crash(self, fake_socket, sample_imei):
        """
        L1 can arrive without lock_scooter() waiting (e.g. geofence lock).
        In that case pending_lock_results has no entry for this IMEI.
        handle_L1 must not crash - just send ack and move on.
        """
        msg = {
            "imei":    sample_imei,
            "command": "L1",
            "data":    ["0", "1234", "9999", "5"],
        }

        server.handle_L1(fake_socket, msg, sample_imei)

        assert len(fake_socket.sent) == 1 


class TestHandleD0:
    """D0 = GPS location packet."""

    def test_forwards_valid_gps_to_laravel(self, fake_socket, sample_imei, mocker):
        """
        Valid GPS fix (data[2]='A') must be converted and forwarded to Laravel.
        Coordinates arrive in DDMM.MMMM format - we convert to decimal degrees.
        """
        mock_post = mocker.patch("server.requests.post")

        msg = {
            "imei":    sample_imei,
            "command": "D0",
            "data":  ["1", "120000.00", "A", "4159.4600", "N", "04145.9000", "E",
                      "8", "1.2", "210526", "4", "M", "A"],
        }

        server.handle_D0(fake_socket, msg)

        mock_post.assert_called_once()

        call_kwargs = mock_post.call_args[1]
        lat = call_kwargs["json"]["lat"]
        lng = call_kwargs["json"]["lng"]

        assert abs(lat - 41.991) < 0.01

        assert abs(lng - 41.765) < 0.01

    def test_skips_invalid_fix(self, fake_socket, sample_imei, mocker):
        """
        data[2]='V' means no satellite lock - GPS coordinates are invalid.
        Must NOT forward garbage coordinates to Laravel.
        """
        mock_post = mocker.patch("server.requests.post")

        msg = {
            "imei":    sample_imei,
            "command": "D0",
            "data":    ["1", "120000.00", "V", "0000.0000", "N", "00000.0000", "E"],
        }

        server.handle_D0(fake_socket, msg)

        mock_post.assert_not_called()

    def test_does_not_crash_when_laravel_unreachable(self, fake_socket, sample_imei, mocker):
        """
        Same as H0 - GPS forward failure must never crash the TCP server.
        """
        mocker.patch("server.requests.post", side_effect=Exception("timeout"))

        msg = {
            "imei":    sample_imei,
            "command": "D0",
            "data":    ["1", "120000.00", "A", "4159.4600", "N", "04145.9000", "E",
                        "8", "1.2", "210526", "4", "M", "A"],
        }

        server.handle_D0(fake_socket, msg)


class TestHandleW0:
    """W0 = alarm event (tip-over, tamper, low battery, etc)."""

    def test_sends_acknowledgment(self, fake_socket, sample_imei):
        """
        W0 always requires an acknowledgment.
        Without it, scooter may repeat the alarm every few seconds.
        """
        msg = {
            "imei":    sample_imei,
            "command": "W0",
            "data":    ["2"],  
        }

        server.handle_W0(fake_socket, msg, sample_imei)

        assert len(fake_socket.sent) == 1
        sent_text = fake_socket.sent[0][2:].decode()

        assert f"*SCOS,OM,{sample_imei},W0#\n" == sent_text

    def test_handles_all_alarm_types(self, fake_socket, sample_imei):
        """
        All alarm codes must be handled without crashing.
        Codes: 1=movement, 2=tip-over, 3=disassembly, 4=low battery,
               6=tip-over cleared, 7=disassembly cleared.
        Unknown codes must also not crash - we just log and ack.
        """
        alarm_codes = ["1", "2", "3", "4", "6", "7", "99"]  

        for code in alarm_codes:
            fresh_socket = type(fake_socket)()  
            msg = {
                "imei":    sample_imei,
                "command": "W0",
                "data":    [code],
            }

            server.handle_W0(fresh_socket, msg, sample_imei)

            assert len(fresh_socket.sent) == 1