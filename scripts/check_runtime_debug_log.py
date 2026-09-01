#!/usr/bin/env python3
"""Fail on runtime debug output except one documented WooCommerce CLI notice."""

from __future__ import annotations

import sys
from pathlib import Path


if len(sys.argv) != 2:
    raise SystemExit("Usage: check_runtime_debug_log.py <debug.log>")

log_path = Path(sys.argv[1])
if not log_path.exists() or log_path.stat().st_size == 0:
    print("WordPress debug log is empty.")
    raise SystemExit(0)

lines = [line.strip() for line in log_path.read_text(encoding="utf-8").splitlines() if line.strip()]
unexpected = []
allowed = []

for line in lines:
    is_known_woocommerce_cli_notice = all(
        token in line
        for token in (
            "PHP Notice:",
            "_load_textdomain_just_in_time",
            "<code>woocommerce</code>",
            "triggered too early",
            "version 6.7.0",
            "/wp-includes/functions.php on line",
        )
    )
    if is_known_woocommerce_cli_notice:
        allowed.append(line)
    else:
        unexpected.append(line)

if len(allowed) > 1:
    unexpected.extend(allowed[1:])
    allowed = allowed[:1]

if unexpected:
    print("Unexpected WordPress runtime debug output:", file=sys.stderr)
    print("\n".join(unexpected), file=sys.stderr)
    raise SystemExit(1)

print(f"Allowed {len(allowed)} documented WooCommerce WP-CLI translation notice(s).")
