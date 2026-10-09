# Test 1: Functional Workflow Test

| Field | Value |
|---|---|
| **Objective** | Show that every core workflow works end to end without errors on the deployed site (report §1.2: 100% of steps pass, tested through the ALB URL). Covers register, log in, report found, browse, report lost + matches, claim, admin approve/reject and log out. |
| **Date** | Local run: 2026-10-08 19:31 (Windows laptop, XAMPP: Apache 2.4.58, PHP 8.2, MariaDB 10.4) · ALB run: `YYYY-MM-DD` *(pending deployment)* |
| **Setup** | App deployed as in `README.md`. Database loaded from `data/schema.sql` (includes the demo admin). No other data needed: the script registers its own two students (A and B) with unique emails on every run. |
| **Command** | `python3 tests/run_tests.py http://<alb-dns-name>` (and the same against local XAMPP) |
| **Artefacts** | `tests/run_tests.py`, `evidence/test-run-local-2026-10-08.txt`, `evidence/test-run-alb-YYYY-MM-DD.txt` *(pending)* |

## Steps and expected results

| ID | Workflow | What the script does | Expected result | Actual |
|---|---|---|---|---|
| F01 | Register | Registers Student A with a strong password | Redirect to `login.php?registered=1` | PASS (local) |
| F02 | Register validation | Registers with password `abc` | Rejected with "at least 8 characters"; no account | PASS (local) |
| F03 | Duplicate email | Registers Student A's email again | "An account with that email already exists." | PASS (local) |
| F04 | Log in | Logs in as Student A | Lands on `dashboard.php`, name shown | PASS (local) |
| F05 | Wrong password | Logs in with a wrong password | "Incorrect email or password." | PASS (local) |
| F06 | Report found | Student B reports a blue Hydro Flask bottle at W3 lobby, with a private detail | Redirect to `report_found_success.php` | PASS (local) |
| F07 | Browse | Student A opens Browse | The bottle is listed | PASS (local) |
| F08 | Browse filters | Filters by category + keyword, then a keyword that matches nothing | Bottle shown, then not shown | PASS (local) |
| F09 | Report lost + matches | Student A reports the bottle lost | Redirect to matches page; the bottle is suggested with a "Match score" of ≥ 20% | PASS (local) |
| F10 | Claim | Student A claims it with a verification answer | `claim_success.php`; My Claims shows PENDING | PASS (local) |
| F11 | Finder view | Student B opens My Claims | Sees Student A's claim on their item | PASS (local) |
| F12 | Admin review | Admin opens Admin Panel | Claimant's answer shown next to the finder's private detail | PASS (local) |
| F13 | Approve | Admin approves | Claim leaves the pending list | PASS (local) |
| F14 | Status update | Student A opens My Claims | APPROVED | PASS (local) |
| F15 | Item closed | Student A opens Browse | The approved bottle is no longer listed | PASS (local) |
| F16 | Reject path | Repeats with a backpack; admin rejects | My Claims shows REJECTED; backpack still in Browse | PASS (local) |
| F17 | Log out | Logs out, then opens the dashboard | Redirect to `login.php` | PASS (local) |

## Actual result

| Run | Target | Functional steps passed | Log |
|---|---|---|---|
| Local, 2026-10-08 19:31 | `http://localhost/INF2006-app/src/public` | **17 / 17** | `evidence/test-run-local-2026-10-08.txt` |
| Deployed | ALB URL | __ / 17 *(pending)* | `evidence/test-run-alb-YYYY-MM-DD.txt` |

Local log summary line: `SUMMARY: 33/33 checks passed, 0 failed` (17 functional + 16 security).
In F09 the lost bottle was matched to the correct found bottle at **81%**, with an unrelated item
shown second at 32%, so the ranking behaved as intended.

**Target (100% of steps pass through the ALB URL): met locally; ALB run pending deployment.**

## Notes

- One script covers both this test and the security test (`S..` lines in the same log), so a single run produces the evidence for both.
- Before this run, pages hung on the Windows test laptop. Running `health.php` directly showed the
  local MariaDB server had frozen and then would not restart (damaged `mysql` system tables); it was
  repaired from XAMPP's clean copy, and the `inf2006` data was intact. This was a local XAMPP fault,
  not an app fault. During the investigation we also found that `python3` on Windows is a Microsoft
  Store placeholder rather than real Python, so `src/inc/matching.php` now calls `python` (or the
  `py` launcher) on Windows and `python3` on Linux/EC2.
- During development (2026-10-08), a first version of the script reported F15 as failed. Cause: the Browse page echoes the search keyword back into the search box, so the script found its own search term on the page. The script was fixed to look for the item's description instead; the app was not at fault.
