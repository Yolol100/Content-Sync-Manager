#!/usr/bin/env python3
"""Build a deterministic, runtime-only Content Sync Manager release ZIP."""

from __future__ import annotations

import hashlib
import os
import re
import subprocess
import time
import zipfile
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
SLUG = "content-sync-manager"
MAIN_FILE = ROOT / "content-sync-manager.php"


def match(pattern: str, text: str, label: str) -> str:
    result = re.search(pattern, text, flags=re.MULTILINE)
    if not result:
        raise SystemExit(f"Unable to read {label}.")
    return result.group(1)


main_text = MAIN_FILE.read_text(encoding="utf-8")
readme_text = (ROOT / "readme.txt").read_text(encoding="utf-8")
version = match(r"^ \* Version:\s*([^\s]+)", main_text, "plugin header version")
stable_tag = match(r"^Stable tag:\s*([^\s]+)", readme_text, "stable tag")
constant_version = match(
    r"^define\('DCA_TB_VERSION',\s*'([^']+)'\);",
    main_text,
    "DCA_TB_VERSION",
)

if len({version, stable_tag, constant_version}) != 1:
    raise SystemExit(
        "Version mismatch: "
        f"header={version} stable_tag={stable_tag} constant={constant_version}"
    )

release_tag = os.environ.get("RELEASE_TAG")
if release_tag and release_tag != f"v{version}":
    raise SystemExit(f"Tag {release_tag} does not match plugin version v{version}.")

source_date_epoch = os.environ.get("SOURCE_DATE_EPOCH")
if source_date_epoch is None:
    source_date_epoch = subprocess.check_output(
        ["git", "-C", str(ROOT), "log", "-1", "--format=%ct"], text=True
    ).strip()
timestamp = max(int(source_date_epoch), 315532800)  # ZIP timestamps start in 1980.
zip_datetime = time.gmtime(timestamp)[:6]

runtime_paths = [
    MAIN_FILE,
    ROOT / "uninstall.php",
    ROOT / "readme.txt",
    ROOT / "LICENSE",
]
for runtime_dir in (ROOT / "assets", ROOT / "includes"):
    runtime_paths.extend(path for path in runtime_dir.rglob("*") if path.is_file())
runtime_paths.sort(key=lambda path: path.relative_to(ROOT).as_posix())

dist = ROOT / "dist"
dist.mkdir(exist_ok=True)
archive = dist / f"{SLUG}-{version}.zip"
checksum_file = archive.with_suffix(archive.suffix + ".sha256")

with zipfile.ZipFile(
    archive, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9
) as package:
    for source in runtime_paths:
        relative = source.relative_to(ROOT).as_posix()
        info = zipfile.ZipInfo(f"{SLUG}/{relative}", date_time=zip_datetime)
        info.compress_type = zipfile.ZIP_DEFLATED
        info.create_system = 3
        info.external_attr = 0o100644 << 16
        package.writestr(info, source.read_bytes(), compresslevel=9)

with zipfile.ZipFile(archive) as package:
    names = package.namelist()
    required = f"{SLUG}/content-sync-manager.php"
    if required not in names:
        raise SystemExit(f"Release archive is missing {required}.")
    forbidden = re.compile(
        r"(^|/)(\.git|\.github|tests|scripts)(/|$)|AUDIT-|README\.md$|CHANGELOG\.md$"
    )
    unexpected = [name for name in names if forbidden.search(name)]
    if unexpected:
        raise SystemExit(f"Release archive contains development files: {unexpected}")

checksum = hashlib.sha256(archive.read_bytes()).hexdigest()
checksum_file.write_text(f"{checksum}  {archive.name}\n", encoding="ascii", newline="\n")
print(f"Built {archive}")
print(f"SHA-256 {checksum}")
