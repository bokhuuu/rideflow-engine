
# ─────────────────────────────────────────────────────────────────────────────
# Unit tests for parse_message() in server.py.
#
# parse_message() is the first thing that touches every incoming scooter packet.
# If it breaks, nothing works.
#
#   - Every real command type the scooter sends (Q0, H0, R0, L0, L1, D0, W0)
#   - Edge cases: malformed input, short messages, empty strings
# ─────────────────────────────────────────────────────────────────────────────

import server


class TestParseMessage:
    """
    Groups all parse_message() tests together.
    pytest collects classes starting with Test* automatically.
    """

    def test_q0_checkin(self, sample_imei):
        """
        Q0 = first message a scooter sends after connecting.
        Format: *SCOR,OM,{IMEI},Q0,{voltage},{battery},{signal}#
        We expect imei, command="Q0", and 3 data fields.
        """
        raw = f"*SCOR,OM,{sample_imei},Q0,412,80,28#"

        result = server.parse_message(raw)

        assert result is not None

        assert result["imei"] == sample_imei

        assert result["command"] == "Q0"

        assert result["data"] == ["412", "80", "28"]

    def test_h0_heartbeat(self, sample_imei):
        """
        H0 = heartbeat sent every 4 minutes.
        Format: *SCOR,OM,{IMEI},H0,{status},{voltage},{signal},{battery},{charging}#
        data[3] = battery %, data[4] = charging status - both used in handle_H0.
        """
        raw = f"*SCOR,OM,{sample_imei},H0,1,412,28,80,0#"

        result = server.parse_message(raw)

        assert result is not None
        assert result["command"] == "H0"
        
        assert len(result["data"]) == 5
        assert result["data"][3] == "80"  
        assert result["data"][4] == "0"  

    def test_r0_key_response(self, sample_imei):
        """
        R0 = scooter's reply to our unlock/lock key request.
        Format: *SCOR,OM,{IMEI},R0,{operation},{KEY},{user_id},{timestamp}#
        data[1] = KEY - must be forwarded to L0/L1 exactly as received.
        """
        raw = f"*SCOR,OM,{sample_imei},R0,0,55,1234,1497689816#"

        result = server.parse_message(raw)

        assert result is not None
        assert result["command"] == "R0"
        assert result["data"][0] == "0"          
        assert result["data"][1] == "55"          
        assert result["data"][2] == "1234"      
        assert result["data"][3] == "1497689816"  

    def test_l0_unlock_result(self, sample_imei):
        """
        L0 = scooter's unlock confirmation.
        data[0] = 0 (success), 1 (failure), 2 (key error).
        """
        raw = f"*SCOR,OM,{sample_imei},L0,0,1234,1497689816#"

        result = server.parse_message(raw)

        assert result is not None
        assert result["command"] == "L0"
        assert result["data"][0] == "0" 

    def test_l1_lock_result(self, sample_imei):
        """
        L1 = scooter's lock confirmation.
        data[3] = ride duration in minutes - extracted in handle_L1.
        """
        raw = f"*SCOR,OM,{sample_imei},L1,0,1234,1497689816,5#"

        result = server.parse_message(raw)

        assert result is not None
        assert result["command"] == "L1"
        assert result["data"][0] == "0"
        assert result["data"][3] == "5"

    def test_d0_gps(self, sample_imei):
        """
        D0 = GPS location packet.
        Format has many fields - we just check command and data presence.
        Coordinate conversion is tested separately in test_handlers.py.
        """
        raw = f"*SCOR,OM,{sample_imei},D0,1,120000.00,A,4159.4600,N,04145.9000,E,8,1.2,210526,4,M,A#"

        result = server.parse_message(raw)

        assert result is not None
        assert result["command"] == "D0"
        
        assert result["data"][2] == "A"

    def test_w0_alarm(self, sample_imei):
        """
        W0 = alarm event (tip-over, tamper, low battery, etc).
        data[0] = alarm type code.
        """
        raw = f"*SCOR,OM,{sample_imei},W0,2#" 

        result = server.parse_message(raw)

        assert result is not None
        assert result["command"] == "W0"
        assert result["data"][0] == "2"

    def test_strips_trailing_hash(self, sample_imei):
        """
        Protocol ends every packet with #\n.
        parse_message() must strip # so data fields don't contain it.
        Without this, data[-1] would be "28#" instead of "28".
        """
        raw = f"*SCOR,OM,{sample_imei},Q0,412,80,28#"
        result = server.parse_message(raw)

        assert "#" not in result["data"][-1]

    def test_strips_whitespace(self, sample_imei):
        """
        TCP can deliver packets with leading/trailing whitespace or \r\n.
        parse_message() must handle this gracefully.
        """
        raw = f"  *SCOR,OM,{sample_imei},Q0,412,80,28#\r\n  "
        result = server.parse_message(raw)

        assert result is not None
        assert result["command"] == "Q0"

    def test_returns_none_for_too_short(self):
        """
        A message with fewer than 4 comma-separated parts is malformed.
        parse_message() must return None - not crash.
        Malformed packets arrive in practice (network noise, partial sends).
        """
        result = server.parse_message("*SCOR,OM,123#")
        assert result is None

    def test_returns_none_for_empty_string(self):
        """
        Empty string should return None, not raise an exception.
        handle_client() calls parse_message() on every line - empty lines happen.
        """
        result = server.parse_message("")
        assert result is None

    def test_returns_none_for_garbage(self):
        """
        Random garbage bytes / network noise must not crash the parser.
        """
        result = server.parse_message("GARBAGE DATA %%%###")
        assert result is None

    def test_no_data_fields(self, sample_imei):
        """
        Some acknowledgment packets have no data after the command.
        Example: server sends *SCOS,OM,{IMEI},L0# - scooter echoes it back.
        data should be an empty list, not crash.
        """
        raw = f"*SCOR,OM,{sample_imei},L0#"
        result = server.parse_message(raw)

        assert result is not None
        assert result["command"] == "L0"
        assert result["data"] == []