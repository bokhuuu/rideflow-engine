# core/__init__.py
# ─────────────────────────────────────────────────────────────────────────────
# Marks core/ as a Python package.
# core/ contains device-agnostic TCP infrastructure:
#   - packet_parser.py  → parse raw bytes into dicts
#   - command_sender.py → actions triggered by Laravel (unlock, lock, beep)
#   - connection_pool.py → tracks connected scooter sockets by IMEI
#   - tcp_server.py     → accept loop + per-client thread
# ─────────────────────────────────────────────────────────────────────────────