#!/usr/bin/env python3
"""
Load generator for Test 4 part B (scale-out). Drives CPU up on the web tier so the
Auto Scaling target-tracking policy (average CPU 50%) adds instances.

It logs in as a fresh test student, creates one lost report, and then many threads
repeatedly open that report's matches page. Each request runs the Python TF-IDF matcher
on the server, which is the most CPU-heavy page in the app.

Usage:
    pip install -r tests/requirements.txt
    python3 tests/load_test.py http://<alb-dns-name> --threads 30 --minutes 12

Prints a line every 30 s: requests, errors, requests/second and p50/p95 latency.
Run it from CloudShell or a laptop. Stop early with Ctrl+C.
"""
import argparse
import statistics
import threading
import time
from collections import Counter
from datetime import datetime, date
from urllib.parse import urlparse, parse_qs

import requests

ap = argparse.ArgumentParser()
ap.add_argument("base_url")
ap.add_argument("--threads", type=int, default=30)
ap.add_argument("--minutes", type=float, default=12)
args = ap.parse_args()
BASE = args.base_url.rstrip("/")
REPORT_EVERY = 30
run = datetime.now().strftime("%Y%m%d%H%M%S")

# One logged-in session; its cookies (PHP session + ALB stickiness) are copied to each worker
setup = requests.Session()
email = f"load.{run}@test.sit.local"
setup.post(f"{BASE}/register.php", data={"name": "Load Test", "email": email, "password": "Lost&Found1"}, timeout=30)
setup.post(f"{BASE}/login.php", data={"email": email, "password": "Lost&Found1"}, timeout=30)
r = setup.post(f"{BASE}/report_lost.php", data={
    "category": "Bottle/Container", "description": "Blue Hydro Flask water bottle with a dent",
    "location": "W3", "item_date": date.today().isoformat()}, timeout=60)
item_id = parse_qs(urlparse(r.url).query).get("item_id", [None])[0]
if not item_id:
    raise SystemExit(f"Setup failed (could not create a lost report); last URL was {r.url}")
target = f"{BASE}/matches.php?item_id={item_id}"
print(f"Target: {target}")
print(f"{args.threads} threads for {args.minutes} min, started {datetime.now():%H:%M:%S}\n")

lock = threading.Lock()
latencies, statuses = [], Counter()
stop_at = time.time() + args.minutes * 60


def worker():
    s = requests.Session()
    s.cookies.update(setup.cookies)
    while time.time() < stop_at:
        t0 = time.time()
        try:
            code = s.get(target, timeout=30).status_code
        except requests.RequestException:
            code = "ERR"
        with lock:
            latencies.append(time.time() - t0)
            statuses[code] += 1


threads = [threading.Thread(target=worker, daemon=True) for _ in range(args.threads)]
for t in threads:
    t.start()

try:
    while time.time() < stop_at:
        time.sleep(REPORT_EVERY)
        with lock:
            lat, st = latencies[:], dict(statuses)
            latencies.clear()
            statuses.clear()
        n = len(lat)
        errors = sum(v for k, v in st.items() if k != 200)
        if n:
            p95 = sorted(lat)[int(0.95 * (n - 1))]
            print(f"{datetime.now():%H:%M:%S}  requests={n:5d}  errors={errors:4d}  "
                  f"rps={n / REPORT_EVERY:6.1f}  p50={statistics.median(lat):.2f}s  p95={p95:.2f}s  {st}")
        else:
            print(f"{datetime.now():%H:%M:%S}  no completed requests")
except KeyboardInterrupt:
    print("Stopped by user")
print(f"\nFinished {datetime.now():%H:%M:%S}")
