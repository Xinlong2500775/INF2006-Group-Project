#!/usr/bin/env python3
"""
S18: check that no credentials are committed to the repository.

Scans every tracked text file for things that look like secrets (AWS keys,
private keys, RDS endpoints with passwords, hard-coded DB passwords) and
confirms the real config files are gitignored.

Usage (from the repository root):
    python3 tests/check_secrets.py

Exit code 0 = no secrets found.
"""
import os
import re
import subprocess
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))

PATTERNS = {
    "AWS access key ID": re.compile(r"\b(AKIA|ASIA)[0-9A-Z]{16}\b"),
    "AWS secret key assignment": re.compile(r"aws_secret_access_key\s*[=:]\s*\S{20,}", re.I),
    "Private key block": re.compile(r"-----BEGIN [A-Z ]*PRIVATE KEY-----"),
    "RDS endpoint (should not be committed)": re.compile(r"[a-z0-9-]+\.[a-z0-9]+\.[a-z0-9-]+\.rds\.amazonaws\.com"),
}

# DB passwords written as   DB_PASSWORD=value   (.env style)
# or as                     define('DB_PASSWORD', 'value')   (PHP style)
PASSWORD_ASSIGNMENTS = [
    re.compile(r"^\s*DB_PASSWORD\s*=\s*(?P<v>.*?)\s*$", re.M),
    re.compile(r"define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](?P<v>[^'\"]*)['\"]"),
]
PLACEHOLDERS = re.compile(r"^(|\$.*|change[-_ ]?me.*|<.*>|your[-_].*|x+)$", re.I)

# Files that intentionally contain placeholders or describe these patterns
SKIP = {"tests/check_secrets.py"}


def tracked_files():
    try:
        out = subprocess.run(["git", "ls-files"], cwd=ROOT, capture_output=True, text=True, check=True)
        return [f for f in out.stdout.splitlines() if f]
    except (subprocess.CalledProcessError, FileNotFoundError):
        # Not a git checkout (e.g. unzipped submission): scan every file
        files = []
        for d, _, names in os.walk(ROOT):
            if ".git" in d.split(os.sep):
                continue
            files += [os.path.relpath(os.path.join(d, n), ROOT) for n in names]
        return files


problems = []
files = [f.replace(os.sep, "/") for f in tracked_files()]
for rel in files:
    if rel in SKIP:
        continue
    path = os.path.join(ROOT, rel)
    try:
        with open(path, encoding="utf-8") as fh:
            text = fh.read()
    except (UnicodeDecodeError, OSError):
        continue  # binary file
    for pat in PASSWORD_ASSIGNMENTS:
        for m in pat.finditer(text):
            value = m.group("v").strip().strip("'\"")
            if not PLACEHOLDERS.match(value):
                line = text[:m.start()].count("\n") + 1
                problems.append(f"{rel}:{line}: hard-coded DB password")
    for name, pat in PATTERNS.items():
        for m in pat.finditer(text):
            line = text[:m.start()].count("\n") + 1
            problems.append(f"{rel}:{line}: {name}")

for must_not_exist in ("src/.env", "src/inc/dbinfo.inc.php"):
    if must_not_exist in files:
        problems.append(f"{must_not_exist}: real config file is tracked by git")

with open(os.path.join(ROOT, ".gitignore"), encoding="utf-8") as fh:
    ignored = fh.read()
for entry in ("src/.env", "src/inc/dbinfo.inc.php"):
    if entry not in ignored:
        problems.append(f".gitignore: missing entry {entry}")

print(f"Scanned {len(files)} files.")
if problems:
    print("FAIL: possible secrets found:")
    print("\n".join("  " + p for p in problems))
    sys.exit(1)
print("PASS S18: no credentials found; src/.env and src/inc/dbinfo.inc.php are gitignored and not tracked.")
