#!/usr/bin/env python3
"""
Automated functional + security tests for Campus Lost & Found.

Drives the real web app over HTTP, the same way a browser does, so it works
against a local XAMPP install or the deployed ALB URL. Every run creates fresh
test accounts (unique emails), so it can be repeated any number of times.

Usage:
    pip install -r tests/requirements.txt
    python3 tests/run_tests.py http://localhost/INF2006-app/src/public
    python3 tests/run_tests.py http://<alb-dns-name>

Admin login defaults to the demo account in data/schema.sql. Override with
environment variables ADMIN_EMAIL and ADMIN_PASSWORD if it was changed.

Output: one line per check (PASS/FAIL), a summary, and a copy of the log in
tests/output/. Exit code 0 only if every check passed.

Test IDs match evidence/test-functional.md (F..) and evidence/test-security.md (S..).
"""
import os
import re
import sys
import time
from datetime import datetime, date
from urllib.parse import urlparse, parse_qs

import requests

if len(sys.argv) < 2:
    sys.exit(__doc__)

BASE = sys.argv[1].rstrip("/")
ADMIN_EMAIL = os.environ.get("ADMIN_EMAIL", "admin@sit.singaporetech.edu.sg")
ADMIN_PASSWORD = os.environ.get("ADMIN_PASSWORD", "admin123")
RUN_ID = datetime.now().strftime("%Y%m%d%H%M%S")
TODAY = date.today().isoformat()
STRONG_PW = "Lost&Found1"

results = []
log_lines = []


def log(line=""):
    print(line)
    log_lines.append(line)


def check(test_id, description, passed, detail=""):
    status = "PASS" if passed else "FAIL"
    results.append((test_id, status))
    log(f"[{status}] {test_id:4} {description}" + (f"  ({detail})" if detail else ""))


def url(page):
    return f"{BASE}/{page}"


def new_session():
    s = requests.Session()
    s.headers["User-Agent"] = "lostfound-tests/1.0"
    return s


def register(s, name, email, password):
    return s.post(url("register.php"),
                  data={"name": name, "email": email, "password": password},
                  allow_redirects=True, timeout=20)


def login(s, email, password):
    return s.post(url("login.php"), data={"email": email, "password": password},
                  allow_redirects=True, timeout=20)


def report_found(s, category, description, location, private_detail):
    return s.post(url("report_found.php"), data={
        "category": category, "description": description, "location": location,
        "item_date": TODAY, "private_detail": private_detail}, timeout=20)


def report_lost(s, category, description, location):
    """Returns (response, new lost item_id) after following the redirect to matches.php."""
    r = s.post(url("report_lost.php"), data={
        "category": category, "description": description, "location": location,
        "item_date": TODAY}, timeout=30)
    item_id = parse_qs(urlparse(r.url).query).get("item_id", [None])[0]
    return r, item_id


def id_in_card(html, marker, field):
    """Value of hidden input `field` inside the item card whose text contains `marker`."""
    for card in html.split('class="item-card"')[1:]:
        if marker in card:
            m = re.search(rf'name="{field}" value="(\d+)"', card)
            if m:
                return m.group(1)
    return None


def claim(s, lost_id, found_id, answer):
    return s.post(url("claim.php"), data={
        "lost_item_id": lost_id, "found_item_id": found_id,
        "verification_answer": answer}, timeout=20)


def pending_claim_ids(admin_html):
    return re.findall(r'name="claim_id" value="(\d+)"', admin_html)


log("Campus Lost & Found automated tests")
log(f"Target: {BASE}")
log(f"Run:    {datetime.now().isoformat(timespec='seconds')}  (run id {RUN_ID})")
log("")

# Unique words per run so this run's items are easy to find among old data
tag = f"Zx{RUN_ID[-6:]}"
found_desc = f"Blue Hydro Flask water bottle {tag} with a dent on the side"
lost_desc = f"Lost my blue Hydro Flask {tag} bottle, it has a dent"
secret = f"Pokemon sticker on the bottom {tag}"

a, b, admin, anon = new_session(), new_session(), new_session(), new_session()
email_a = f"student.a.{RUN_ID}@test.sit.local"
email_b = f"student.b.{RUN_ID}@test.sit.local"

# ---------------------------------------------------------------------------
log("== Functional workflow (evidence/test-functional.md) ==")
r = register(a, "Student A", email_a, STRONG_PW)
check("F01", "Register a new student", "login.php" in r.url and "registered" in r.url, r.url)

r = register(new_session(), "Weak", f"weak.{RUN_ID}@test.sit.local", "abc")
check("F02", "Weak password rejected", "at least 8 characters" in r.text and "register.php" in r.url)

r = register(new_session(), "Dup", email_a, STRONG_PW)
check("F03", "Duplicate email rejected", "already exists" in r.text)

r = login(a, email_a, STRONG_PW)
check("F04", "Log in as Student A", "dashboard.php" in r.url and "Student A" in r.text, r.url)

r = login(new_session(), email_a, "WrongPass1!")
check("F05", "Wrong password rejected", "Incorrect email or password" in r.text)

register(b, "Student B", email_b, STRONG_PW)
login(b, email_b, STRONG_PW)
r = report_found(b, "Bottle/Container", found_desc, "W3 lobby", secret)
check("F06", "Student B reports a found item", "report_found_success.php" in r.url, r.url)

r = a.get(url("browse.php"), timeout=20)
check("F07", "Found item visible in Browse", tag in r.text)

r = a.get(url("browse.php"), params={"category": "Bottle/Container", "keyword": tag}, timeout=20)
r2 = a.get(url("browse.php"), params={"keyword": "no-such-item-" + RUN_ID}, timeout=20)
check("F08", "Browse filters narrow the results", tag in r.text and tag not in r2.text)

r, lost_id = report_lost(a, "Bottle/Container", lost_desc, "W3")
found_id = id_in_card(r.text, tag, "found_item_id")
pct = re.findall(r"Match score: (\d+)%", r.text)
check("F09", "Lost report shows the found item as a match with a %",
      lost_id is not None and found_id is not None and all(int(p) >= 20 for p in pct),
      f"lost_id={lost_id}, found_id={found_id}, scores={pct[:5]}")

r = claim(a, lost_id, found_id, "There's a Pokemon sticker on the bottom")
r_claims = a.get(url("my_claims.php"), timeout=20)
check("F10", "Student A submits a claim (PENDING)",
      "claim_success.php" in r.url and "PENDING" in r_claims.text, r.url)

r = b.get(url("my_claims.php"), timeout=20)
check("F11", "Finder sees the claim on their item", "Student A" in r.text and tag in r.text)

login(admin, ADMIN_EMAIL, ADMIN_PASSWORD)
r = admin.get(url("admin.php"), timeout=20)
check("F12", "Admin sees claim with private detail side by side",
      secret in r.text and "Pokemon sticker" in r.text)

target_claim = id_in_card(r.text, tag, "claim_id")
r = admin.post(url("admin.php"), data={"claim_id": target_claim, "action": "approve"}, timeout=20)
check("F13", "Admin approves the claim", target_claim is not None and tag not in admin.get(url("admin.php")).text,
      f"claim_id={target_claim}")

r = a.get(url("my_claims.php"), timeout=20)
check("F14", "Claimant sees APPROVED", "APPROVED" in r.text)

r = a.get(url("browse.php"), timeout=20)
check("F15", "Approved found item no longer listed in Browse", found_desc not in r.text)

# F16: reject path
tag2 = tag + "R"
report_found(b, "Bag", f"Grey backpack {tag2} with laptop sleeve", "Library level 2", f"Name tag inside {tag2}")
r, lost2 = report_lost(a, "Bag", f"Grey backpack {tag2} laptop sleeve", "Library")
found2 = id_in_card(r.text, tag2, "found_item_id")
claim(a, lost2, found2, "It has my name on a tag inside")
r = admin.get(url("admin.php"), timeout=20)
cid2 = id_in_card(r.text, tag2, "claim_id")
admin.post(url("admin.php"), data={"claim_id": cid2, "action": "reject"}, timeout=20)
r = a.get(url("my_claims.php"), timeout=20)
r2 = a.get(url("browse.php"), timeout=20)
check("F16", "Rejected claim shows REJECTED, item stays in Browse",
      "REJECTED" in r.text and f"Grey backpack {tag2}" in r2.text, f"claim_id={cid2}")

s_out = new_session()
login(s_out, email_a, STRONG_PW)
s_out.get(url("logout.php"), timeout=20)
r = s_out.get(url("dashboard.php"), timeout=20)
check("F17", "After logout, protected pages redirect to login", "login.php" in r.url)

# ---------------------------------------------------------------------------
log("")
log("== Security controls (evidence/test-security.md) ==")

# Setup: Student B has a lost report of their own
r, b_lost = report_lost(b, "Electronics", f"Black earphones {tag} in a case", "LT1")
b_found = found2  # Student B's found item (still open after the reject)

r = a.get(url("matches.php"), params={"item_id": b_lost}, timeout=20)
check("S01", "A cannot view B's matches via URL (matches.php?item_id=B_LOST)",
      "Item not found" in r.text and f"Black earphones {tag}" not in r.text)

r = a.get(url("matches.php"), params={"item_id": b_found}, timeout=20)
check("S02", "A cannot open a found item as a lost report", "Item not found" in r.text)

r1 = a.get(url("matches.php"), params={"item_id": "99999999"}, timeout=20)
r2 = a.get(url("matches.php"), params={"item_id": "abc"}, timeout=20)
leak = re.compile(r"Warning|Fatal error|mysqli|SQL syntax|Stack trace", re.I)
check("S03", "Invalid IDs give a clean 'Item not found', no errors leaked",
      "Item not found" in r1.text and "Item not found" in r2.text
      and not leak.search(r1.text) and not leak.search(r2.text))

r = a.get(url("dashboard.php"), params={"filter": "xyz"}, timeout=20)
check("S04", "Dashboard filter tampering shows no other user's items",
      r.status_code == 200 and f"Black earphones {tag}" not in r.text and not leak.search(r.text))

# S05: tamper hidden lost_item_id to B's lost report
r = claim(a, b_lost, b_found, "tampered claim")
check("S05", "Claim with someone else's lost_item_id is refused",
      "doesn't belong to you" in r.text)

# S06: claim an already-matched found item (approved in F13)
r, a_lost3 = report_lost(a, "Bottle/Container", f"Another bottle {tag}", "W3")
r = claim(a, a_lost3, found_id, "tampered claim on matched item")
check("S06", "Claim on an already-matched item is refused", "no longer available" in r.text)

r = claim(a, a_lost3, b_found, "")
check("S07", "Claim with empty verification answer is refused", "prove it's yours" in r.text)

pages = ["dashboard.php", "browse.php", "report_lost.php", "report_found.php",
         "my_claims.php", "matches.php?item_id=1", "admin.php"]
redirected = [p for p in pages if "login.php" in anon.get(url(p), timeout=20).url]
check("S08", "Every protected page redirects anonymous users to login",
      len(redirected) == len(pages), f"{len(redirected)}/{len(pages)} redirected")

r = a.get(url("admin.php"), timeout=20)
check("S09", "Student opening admin.php is denied", "Access denied" in r.text and "claim_id" not in r.text)

claim(a, a_lost3, b_found, "A genuine-looking pending claim for S10")
r = admin.get(url("admin.php"), timeout=20)
some_claim = id_in_card(r.text, f"Another bottle {tag}", "claim_id")
r = a.post(url("admin.php"), data={"claim_id": some_claim or 1, "action": "approve"}, timeout=20)
still_pending = some_claim is None or some_claim in pending_claim_ids(admin.get(url("admin.php")).text)
check("S10", "Student POSTing an admin approve is denied, claim unchanged",
      some_claim is not None and "Access denied" in r.text and still_pending, f"claim_id={some_claim}")

leaked = [p for p in ["browse.php", f"matches.php?item_id={lost_id}", "my_claims.php", "dashboard.php"]
          if secret in a.get(url(p), timeout=20).text]
check("S11", "Finder's private detail never shown to students", not leaked,
      f"leaked on: {leaked}" if leaked else "checked 4 pages")

r1 = login(new_session(), email_a, "WrongPass1!")
r2 = login(new_session(), f"nobody.{RUN_ID}@test.sit.local", "WrongPass1!")
check("S12", "Same error for wrong password and unknown email",
      "Incorrect email or password" in r1.text and "Incorrect email or password" in r2.text)

r = login(new_session(), "' OR '1'='1' -- ", "anything")
check("S13", "SQL injection in login fails", "dashboard.php" not in r.url and "Incorrect email or password" in r.text)

r = a.get(url("browse.php"), params={"keyword": "' OR 1=1 -- "}, timeout=20)
check("S14", "SQL injection in browse search is treated as text",
      r.status_code == 200 and tag2 not in r.text and not leak.search(r.text))

xss = f"<script>alert('xss{tag}')</script>"
r, xss_id = report_lost(a, "Other", xss, "Test")
d = a.get(url("dashboard.php"), timeout=20)
check("S15", "Stored XSS payload is escaped, not executed",
      xss not in r.text and xss not in d.text and "&lt;script&gt;" in d.text)

r = register(new_session(), "Weak2", f"weak2.{RUN_ID}@test.sit.local", "password")
r2 = login(new_session(), f"weak2.{RUN_ID}@test.sit.local", "password")
check("S16", "Weak password rejected by the server (not just the browser)",
      "at least 1 uppercase" in r.text and "dashboard.php" not in r2.url)

log("")
log("S17 (password hashing) and S18 (no credentials in repo) are not HTTP checks:")
log("see tests/check_password_hashes.sql and tests/check_secrets.py.")

# ---------------------------------------------------------------------------
passed = sum(1 for _, s in results if s == "PASS")
log("")
log(f"SUMMARY: {passed}/{len(results)} checks passed, {len(results) - passed} failed")

os.makedirs(os.path.join(os.path.dirname(__file__), "output"), exist_ok=True)
out = os.path.join(os.path.dirname(__file__), "output", f"results-{RUN_ID}.txt")
with open(out, "w", encoding="utf-8") as f:
    f.write("\n".join(log_lines) + "\n")
print(f"Log saved to {os.path.relpath(out)}")

sys.exit(0 if passed == len(results) else 1)
