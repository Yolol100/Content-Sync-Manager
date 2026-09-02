#!/usr/bin/env python3
"""Fail on runtime debug output except narrowly documented upstream CLI notices."""

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
allowed_woocommerce = []
allowed_wp_cli = []

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
    is_known_wp_cli_php85_notice = all(
        token in line
        for token in (
            "PHP Deprecated:",
            "Using null as an array offset is deprecated, use an empty string instead",
            "wp-cli.phar/vendor/wp-cli/php-cli-tools/lib/cli/Colors.php on line 95",
        )
    )

    if is_known_woocommerce_cli_notice:
        allowed_woocommerce.append(line)
    elif is_known_wp_cli_php85_notice:
        allowed_wp_cli.append(line)
    else:
        unexpected.append(line)

if len(allowed_woocommerce) > 1:
    unexpected.extend(allowed_woocommerce[1:])
    allowed_woocommerce = allowed_woocommerce[:1]

if unexpected:
    print("Unexpected WordPress runtime debug output:", file=sys.stderr)
    print("\n".join(unexpected), file=sys.stderr)
    raise SystemExit(1)

print(
    "Allowed "
    f"{len(allowed_woocommerce)} documented WooCommerce WP-CLI translation notice(s) and "
    f"{len(allowed_wp_cli)} exact WP-CLI PHP 8.5 deprecation notice(s)."
)
