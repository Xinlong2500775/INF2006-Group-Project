# Tests

Repeatable tests for the required functional and security checks. They drive the real app
over HTTP, so the same command works against a local install and against the deployed ALB.

| File | Covers | Evidence write-up |
|---|---|---|
| `run_tests.py` | 17 functional steps (F01 to F17) + 16 security checks (S01 to S16) | `evidence/test-functional.md`, `evidence/test-security.md` |
| `check_password_hashes.sql` | S17: every stored password is a bcrypt hash | `evidence/test-security.md` |
| `check_secrets.py` | S18: no credentials committed; real config files gitignored | `evidence/test-security.md` |
| `load_test.py` | Test 4 part B: sustained load to trigger Auto Scaling scale-out | `evidence/test-resilience.md` |

The data/AI test is `analytics/match.py` (see `evidence/test-data-ai.md`). The resilience
test is a manual AWS procedure (see `evidence/test-resilience.md`).

## Run

```bash
pip install -r tests/requirements.txt

# Local (XAMPP: adjust the path to where src/public is served)
python3 tests/run_tests.py http://localhost/INF2006-app/src/public

# Deployed
python3 tests/run_tests.py http://<alb-dns-name>

python3 tests/check_secrets.py
mysql -h <db-host> -u <user> -p inf2006 < tests/check_password_hashes.sql
```

`run_tests.py` needs the demo admin account from `data/schema.sql`. If its password was
changed, set `ADMIN_EMAIL` and `ADMIN_PASSWORD` environment variables first.

Each run creates its own test accounts and items with a unique tag, so it can be repeated on
a database that already has data. It prints PASS/FAIL per check, exits with code 0 only if
everything passed, and saves the log to `tests/output/` (gitignored; dated copies used as
evidence are saved in `evidence/`).

## Proof the tests catch real bugs

On 2026-10-08 the ownership check in `matches.php` was deliberately broken
(`reported_by = ?` changed to `(reported_by = ? OR 1=1)`). `run_tests.py` then reported
`FAIL S01` (32/33). The change was reverted and all 33 passed again.
