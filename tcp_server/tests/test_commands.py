# ─────────────────────────────────────────────────────────────────────────────
# Unit tests for build_command() in server.py.
#
# build_command() constructs every packet the server sends TO the scooter.
# If it builds a wrong packet, the scooter ignores or rejects the command.
#
# Protocol rules:
#   - Must start with 0xFF 0xFF (2 bytes)
#   - Must use *SCOS prefix (server→scooter direction)
#   - Must contain the correct IMEI
#   - Must contain the correct command code
#   - Must correctly join extra params with commas
#   - Must end with #\n
# ─────────────────────────────────────────────────────────────────────────────

import server


class TestBuildCommand:
    """All tests for build_command()."""

    def test_starts_with_0xFFFF(self, sample_imei):
        """
        Protocol spec: server→scooter packets MUST begin with 0xFF 0xFF.
        Scooter firmware uses this as a frame delimiter to detect packet start.
        Without it, the scooter ignores the command entirely.
        """
        result = server.build_command(sample_imei, "V0", "2")

        assert result[0] == 0xFF
        assert result[1] == 0xFF

    def test_uses_scos_prefix(self, sample_imei):
        """
        We always send *SCOS - using *SCOR would confuse the scooter.
        """
        result = server.build_command(sample_imei, "V0", "2")

        text = result[2:].decode()
        assert text.startswith("*SCOS")

    def test_contains_manufacturer_code(self, sample_imei):
        """
        Protocol field 3 = manufacturer code = "OM" for OMNI devices.
        Must always be present between *SCOS and IMEI.
        """
        result = server.build_command(sample_imei, "V0", "2")
        text = result[2:].decode()

        assert ",OM," in text

    def test_contains_imei(self, sample_imei):
        """
        IMEI identifies which scooter to send to.
        If IMEI is wrong, the scooter ignores the command.
        """
        result = server.build_command(sample_imei, "V0", "2")
        text = result[2:].decode()

        assert sample_imei in text

    def test_contains_command(self, sample_imei):
        """
        The command code (Q0, H0, R0, L0, L1, S4, V0, D1, W0) must appear
        exactly where the scooter expects it - after IMEI.
        """
        result = server.build_command(sample_imei, "V0", "2")
        text = result[2:].decode()

        assert ",V0," in text

    def test_ends_with_hash_newline(self, sample_imei):
        """
        Every packet must end with #\n.
        # = end of command marker.
        \n = line terminator the scooter's parser looks for.
        Without \n, the scooter's TCP buffer never flushes the command.
        """
        result = server.build_command(sample_imei, "V0", "2")
        text = result[2:].decode()

        assert text.endswith("#\n")

    def test_r0_unlock_full_format(self, sample_imei):
        """
        R0 unlock = the first step in the unlock handshake.
        Full format: *SCOS,OM,{IMEI},R0,0,20,{user_id},{timestamp}#\n
        params: operation=0 (unlock), validity=20s, user_id, timestamp
        """
        result = server.build_command(sample_imei, "R0", "0", "20", "1234", "9999")
        text = result[2:].decode()

        assert f"*SCOS,OM,{sample_imei},R0,0,20,1234,9999#\n" == text

    def test_l0_with_key(self, sample_imei):
        """
        L0 with KEY = actual unlock command (step 3 of handshake).
        KEY was received from scooter in R0 reply and must match exactly.
        Format: *SCOS,OM,{IMEI},L0,{KEY},{user_id},{timestamp}#\n
        """
        result = server.build_command(sample_imei, "L0", "55", "1234", "9999")
        text = result[2:].decode()

        assert f"*SCOS,OM,{sample_imei},L0,55,1234,9999#\n" == text

    def test_l0_acknowledgment_no_params(self, sample_imei):
        """
        After scooter sends L0 result, server sends bare L0 acknowledgment.
        No params - just the command code.
        Format: *SCOS,OM,{IMEI},L0#\n
        """
        result = server.build_command(sample_imei, "L0")
        text = result[2:].decode()

        assert f"*SCOS,OM,{sample_imei},L0#\n" == text

    def test_s4_speed_cap(self, sample_imei):
        """
        S4 = speed limit control.
        Geofence violation caps all speeds to 6 km/h.
        8 params: speed_meter, cruise, startup, speed_btn, headlight, low, med, high
        """
        result = server.build_command(
            sample_imei, "S4", "0", "0", "0", "0", "0", "6", "6", "6"
        )
        text = result[2:].decode()

        assert f"*SCOS,OM,{sample_imei},S4,0,0,0,0,0,6,6,6#\n" == text

    def test_s4_speed_restore(self, sample_imei):
        """
        S4 restore = normal speed limits after geofence clears.
        low=15, medium=20, high=25 km/h.
        """
        result = server.build_command(
            sample_imei, "S4", "0", "0", "0", "0", "0", "15", "20", "25"
        )
        text = result[2:].decode()

        assert f"*SCOS,OM,{sample_imei},S4,0,0,0,0,0,15,20,25#\n" == text

    def test_v0_beep(self, sample_imei):
        """
        V0 with param 2 = find-vehicle beep (rider hears it to locate scooter).
        V0 with param 1 = out-of-range alert (geofence warning sound).
        """
        result = server.build_command(sample_imei, "V0", "2")
        text = result[2:].decode()

        assert f"*SCOS,OM,{sample_imei},V0,2#\n" == text

    def test_d1_tracking_start(self, sample_imei):
        """
        D1 with interval=10 = start real-time GPS tracking every 10 seconds.
        Used when a ride begins.
        """
        result = server.build_command(sample_imei, "D1", "10")
        text = result[2:].decode()

        assert f"*SCOS,OM,{sample_imei},D1,10#\n" == text

    def test_d1_tracking_stop(self, sample_imei):
        """
        D1 with interval=0 = stop real-time GPS tracking.
        Used when a ride ends.
        """
        result = server.build_command(sample_imei, "D1", "0")
        text = result[2:].decode()

        assert f"*SCOS,OM,{sample_imei},D1,0#\n" == text

    def test_returns_bytes(self, sample_imei):
        """
        build_command() must return bytes, not a string.
        conn.sendall() requires bytes - passing a string raises TypeError.
        """
        result = server.build_command(sample_imei, "V0", "2")
        assert isinstance(result, bytes)

    def test_integer_params_converted(self, sample_imei):
        """
        Params are passed as integers in some places (e.g. int(time.time())).
        build_command() must convert them to strings - bytes don't accept ints.
        """
        result = server.build_command(sample_imei, "R0", 0, 20, 1234, 9999)
        text = result[2:].decode()

        assert "R0,0,20,1234,9999" in text