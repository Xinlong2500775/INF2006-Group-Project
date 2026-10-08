# Test 1: Functional Workflow Test

| Field | Value |
|---|---|
| **Objective** | Show that every core workflow works end to end without errors on the deployed site (report §1.2: 100% of steps pass, tested through the ALB URL). Covers register, log in, report found, browse, report lost + matches, claim, admin approve/reject and log out. |
| **Date** | Local run: `YYYY-MM-DD` · ALB run: `YYYY-MM-DD` *(fill in)* |
| **Setup** | App deployed as in `README.md`. Database loaded from `data/schema.sql` (includes the demo admin). No other data needed: the script registers its own two students (A and B) with unique emails on every run. |
| **Command** | `python3 tests/run_tests.py http://<alb-dns-name>` (and the same against local XAMPP) |
| **Artefacts** | `tests/run_tests.py`, `evidence/test-run-alb-YYYY-MM-DD.txt`, `evidence/test-run-local-YYYY-MM-DD.txt` |

## Steps and expected results

| ID | Workflow | What the script does | Expected result | Actual |
|---|---|---|---|---|
| F01 | Register | Registers Student A with a strong password | Redirect to `login.php?registered=1` | |
| F02 | Register validation | Registers with password `abc` | Rejected with "at least 8 characters"; no account | |
| F03 | Duplicate email | Registers Student A's email again | "An account with that email already exists." | |
| F04 | Log in | Logs in as Student A | Lands on `dashboard.php`, name shown | |
| F05 | Wrong password | Logs in with a wrong password | "Incorrect email or password." | |
| F06 | Report found | Student B reports a blue Hydro Flask bottle at W3 lobby, with a private detail | Redirect to `report_found_success.php` | |
| F07 | Browse | Student A opens Browse | The bottle is listed | |
| F08 | Browse filters | Filters by category + keyword, then a keyword that matches nothing | Bottle shown, then not shown | |
| F09 | Report lost + matches | Student A reports the bottle lost | Redirect to matches page; the bottle is suggested with a "Match score" of ≥ 20% | |
| F10 | Claim | Student A claims it with a verification answer | `claim_success.php`; My Claims shows PENDING | |
| F11 | Finder view | Student B opens My Claims | Sees Student A's claim on their item | |
| F12 | Admin review | Admin opens Admin Panel | Claimant's answer shown next to the finder's private detail | |
| F13 | Approve | Admin approves | Claim leaves the pending list | |
| F14 | Status update | Student A opens My Claims | APPROVED | |
| F15 | Item closed | Student A opens Browse | The approved bottle is no longer listed | |
| F16 | Reject path | Repeats with a backpack; admin rejects | My Claims shows REJECTED; backpack still in Browse | |
| F17 | Log out | Logs out, then opens the dashboard | Redirect to `login.php` | |

## Actual result

*(Paste the summary line from each log here, e.g. `SUMMARY: 33/33 checks passed, 0 failed`, and fill the Actual column with PASS/FAIL per ID.)*

| Run | Target | Functional steps passed | Log |
|---|---|---|---|
| Local | XAMPP | __ / 17 | `evidence/test-run-local-YYYY-MM-DD.txt` |
| Deployed | ALB URL | __ / 17 | `evidence/test-run-alb-YYYY-MM-DD.txt` |

**Target (100% of steps pass through the ALB URL): MET / NOT MET**

## Notes

- One script covers both this test and the security test (`S..` lines in the same log), so a single run produces the evidence for both.
- During development (2026-10-08), a first version of the script reported F15 as failed. Cause: the Browse page echoes the search keyword back into the search box, so the script found its own search term on the page. The script was fixed to look for the item's description instead; the app was not at fault.
