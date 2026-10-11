# Test 2: Security Control Test

| Field | Value |
|---|---|
| **Objective** | Show that no user can see or change another user's reports, matches or claims (report §1.2: **0 successful attempts** when changing item IDs in the URL or in form fields), and that authentication, admin-only access, input validation and secret handling hold against the threats in `evidence/threat-control-map.md`. |
| **Named threat (main)** | **Insecure direct object reference (IDOR), OWASP A01 Broken Access Control:** a logged-in student edits an `item_id` in the URL or a hidden form field to read or claim someone else's item. |
| **Date** | Local run: 2026-10-08 19:31 (Windows laptop, XAMPP) · ALB run: 2026-10-10 18:49 SGT, against `http://lostfound-alb-1449062045.us-east-1.elb.amazonaws.com` |
| **Setup** | Same as Test 1. The script creates Student A and Student B; Student B owns a lost report (B_LOST) and a found report (B_FOUND) that Student A then attacks. |
| **Commands** | `python3 tests/run_tests.py http://<alb-dns-name>` (S01 to S16)<br>`mysql -h <db-host> -u <user> -p inf2006 < tests/check_password_hashes.sql` (S17)<br>`python3 tests/check_secrets.py` (S18) |
| **Artefacts** | `tests/run_tests.py`, `tests/check_password_hashes.sql`, `tests/check_secrets.py`, `evidence/test-run-local-2026-10-08.txt`, `evidence/test-run-alb-2026-10-10.txt`, `evidence/s17-password-hashes-local-2026-10-08.txt`, `evidence/s18-secrets-scan-2026-10-08.txt` |

## A. Changing IDs in the URL (target: 0 successful)

| ID | Attack | Expected (control) | Actual |
|---|---|---|---|
| S01 | Student A opens `matches.php?item_id=B_LOST` | "Item not found." Query requires `reported_by = <session user>` (`matches.php`) | PASS (local, ALB) |
| S02 | Student A opens `matches.php?item_id=B_FOUND` | "Item not found." Query requires `type = 'lost'` | PASS (local, ALB) |
| S03 | `item_id=99999999` and `item_id=abc` | "Item not found."; no PHP/SQL error text leaked (`intval()` + prepared statement) | PASS (local, ALB) |
| S04 | `dashboard.php?filter=xyz` | Normal page; only the student's own items (query filtered by session user) | PASS (local, ALB) |

## B. Changing IDs in form fields (target: 0 successful)

| ID | Attack | Expected (control) | Actual |
|---|---|---|---|
| S05 | Claim with hidden `lost_item_id` changed to B_LOST | "That lost item report doesn't belong to you." No claim row (`claim.php` re-checks ownership server-side) | PASS (local, ALB) |
| S06 | Claim with hidden `found_item_id` set to an already-matched item | "This item is no longer available to claim." (`status = 'open'` check) | PASS (local, ALB) |
| S07 | Claim with an empty verification answer (browser `required` bypassed) | "Please describe something about the item to prove it's yours." | PASS (local, ALB) |

## C. Authentication and authorisation

| ID | Attack | Expected (control) | Actual |
|---|---|---|---|
| S08 | Anonymous request to 7 protected pages | All redirect to `login.php` (`require_login()`) | PASS (local, ALB) |
| S09 | Student opens `admin.php` | "Access denied" (role check before any data is read) | PASS (local, ALB) |
| S10 | Student sends a forged approve POST to `admin.php` for a real pending claim | "Access denied"; claim stays pending (role check runs before the POST handler) | PASS (local, ALB) |
| S11 | Look for the finder's private detail on Browse, Matches, My Claims, Dashboard | Never present (not selected by any student-facing query) | PASS (local, ALB) |
| S12 | Wrong password vs unknown email | Identical message, so registered emails can't be discovered | PASS (local, ALB) |

## D. Injection and input handling

| ID | Attack | Expected (control) | Actual |
|---|---|---|---|
| S13 | Login email `' OR '1'='1' -- ` | Login fails (prepared statement) | PASS (local, ALB) |
| S14 | Browse keyword `' OR 1=1 -- ` | Treated as text, no error, no extra rows (prepared statement) | PASS (local, ALB) |
| S15 | Lost report description `<script>alert(...)</script>` | Shown as text `&lt;script&gt;`, never executed (`htmlentities()` on output) | PASS (local, ALB) |
| S16 | Register with `password` (browser checks bypassed) | Rejected server-side: "at least 1 uppercase..." (`register.php` validation) | PASS (local, ALB) |

## E. Secrets and stored credentials

| ID | Check | Expected (control) | Actual |
|---|---|---|---|
| S17 | `tests/check_password_hashes.sql` | `bcrypt_hashes = total_users`, all hashes 60 characters (`password_hash()`, bcrypt) | PASS: 8 users, 8 bcrypt hashes, all 60 chars |
| S18 | `tests/check_secrets.py` | "PASS S18": no keys/passwords/RDS endpoints in tracked files; `src/.env` gitignored | PASS: 59 tracked files scanned, no credentials |

## Actual result (local 2026-10-08 and deployed ALB 2026-10-10)

| Section | Checks | Passed | Successful attacks |
|---|---|---|---|
| A. IDs in URL | 4 | 4 | 0 |
| B. IDs in form fields | 3 | 3 | 0 |
| C. AuthN / AuthZ | 5 | 5 | 0 |
| D. Injection / input | 4 | 4 | 0 |
| E. Secrets / storage | 2 | 2 | 0 |
| **Total** | **18** | **18** | **0** |

Sections A to D (S01 to S16) passed in both the local run (2026-10-08 19:31) and the run against the
deployed ALB (2026-10-10 18:49, `evidence/test-run-alb-2026-10-10.txt`). S17 and S18 were run locally:
S18 checks the repository, which is what is deployed, and S17 checks the hashing code path, which is
identical on EC2.

**Target (0 successful ID-tampering attempts in A and B): MET (0 of 7 succeeded, locally and on the deployed site).**

## Proof the test detects real failures

On 2026-10-08 the ownership check in `src/public/matches.php` was deliberately broken
(`reported_by = ?` → `(reported_by = ? OR 1=1)`). The script reported **FAIL S01** (32/33).
After reverting, all checks passed again. This shows S01 tests the real control rather than
passing by accident.

## Known limitations (not fixed, documented)

| Gap | Risk | Production fix |
|---|---|---|
| No CSRF tokens on forms | Another website could make a logged-in user submit a form | Per-session CSRF token on every POST form |
| No login rate limiting | Password guessing | Lock out or delay after repeated failures; AWS WAF rate rule on the ALB |
| HTTP only (no TLS) | Traffic readable on shared Wi-Fi | HTTPS listener on the ALB with an ACM certificate (needs a domain, not available in the Learner Lab) |
