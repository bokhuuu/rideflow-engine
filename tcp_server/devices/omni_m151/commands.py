# devices/omni_m151/commands.py
# ─────────────────────────────────────────────────────────────────────────────
# Builds command packets FROM server TO OMNI M151 scooter.
#
# Protocol rules for server→scooter direction:
#   - Must start with 2 bytes: 0xFF 0xFF  (frame delimiter)
#   - Must use *SCOS prefix               (server→IoT direction marker)
#   - Manufacturer code: OM
#   - Format: 0xFFFF*SCOS,OM,{IMEI},{COMMAND},{params}#\n
#
# This module is M151-specific because the 0xFFFF prefix and *SCOS format
# are defined by the OMNI protocol. A different device brand might use a
# completely different wire format.
# ─────────────────────────────────────────────────────────────────────────────


def build_command(imei, command, *args):
    """
    Build a binary command packet to send from server to scooter.

    The *args pattern accepts any number of extra parameters.
    Each parameter is converted to string and joined with commas.

    Example calls:
        build_command("123456789123456", "V0", "2")
        → b'\xff\xff*SCOS,OM,123456789123456,V0,2#\n'

        build_command("123456789123456", "L0")
        → b'\xff\xff*SCOS,OM,123456789123456,L0#\n'

        build_command("123456789123456", "R0", "0", "20", "1234", "9999")
        → b'\xff\xff*SCOS,OM,123456789123456,R0,0,20,1234,9999#\n'

    Args:
        imei    (str): 15-digit scooter identifier.
        command (str): Protocol command code (V0, R0, L0, L1, S4, D1, W0...).
        *args        : Optional parameters for the command - any type, converted to str.

    Returns:
        bytes: Complete binary packet ready to pass to socket.sendall().
    """
    if args:
        # Join all extra params with commas: ("0", "20", "1234") → "0,20,1234"
        payload = ",".join(str(a) for a in args)

        # Full message string with params
        message = f"*SCOS,OM,{imei},{command},{payload}#\n"
    else:
        # No params - bare command with just the end marker
        message = f"*SCOS,OM,{imei},{command}#\n"

    # Prepend the 2-byte frame delimiter: 0xFF 0xFF
    # b'\xff\xff' is a bytes literal - two bytes with value 255
    # message.encode() converts the string to UTF-8 bytes
    # + concatenates the two byte sequences
    return b'\xff\xff' + message.encode()