# core/packet_parser.py
# ─────────────────────────────────────────────────────────────────────────────
# Parses raw text packets FROM scooter TO server into usable dicts.
#
# This module is device-agnostic in structure - it handles the *SCOR envelope
# format that all OMNI devices share. The parsed dict is then handed off to
# device-specific handlers in devices/omni_m151/parser.py.
#
# Input:  "*SCOR,OM,123456789123456,Q0,412,80,28#"
# Output: {"imei": "123456789123456", "command": "Q0", "data": ["412","80","28"]}
# ─────────────────────────────────────────────────────────────────────────────


def parse_message(raw):
    """
    Parse a raw string from the scooter into a structured dict.

    The protocol format is:
        *SCOR,OM,{IMEI},{COMMAND},{data1},{data2},...#

    Steps:
      1. strip() removes leading/trailing whitespace and \r\n
      2. rstrip('#') removes the trailing # end-marker
      3. split(',') splits into parts by comma
      4. We need at least 4 parts: header, manufacturer, imei, command

    Returns None if the message is malformed - callers must check for None.

    Args:
        raw (str): The raw text line received from the scooter over TCP.

    Returns:
        dict | None: Parsed message with keys "imei", "command", "data",
                     or None if the message is malformed.
    """
    # Remove surrounding whitespace (\n, \r, spaces) that TCP may add
    raw = raw.strip()

    # Remove the trailing # end-marker so it doesn't pollute the last data field
    raw = raw.rstrip('#')

    # Split on comma - every field in the protocol is comma-separated
    parts = raw.split(',')

    # A valid packet needs at least: header, manufacturer, imei, command = 4 parts
    # Anything shorter is malformed (noise, partial packet, garbage bytes)
    if len(parts) < 4:
        return None

    return {
        # parts[2] = IMEI - 15-digit unique identifier for this scooter
        "imei": parts[2],

        # parts[3] = command code - Q0, H0, R0, L0, L1, D0, W0, etc.
        "command": parts[3],

        # parts[4:] = all remaining fields - varies per command type
        # Empty list if no data fields (e.g. bare acknowledgment packets)
        "data": parts[4:],
    }