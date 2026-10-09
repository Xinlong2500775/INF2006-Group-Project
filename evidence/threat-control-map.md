# Threat-Control Map

Each threat is mapped to the control that addresses it, where the control lives, and the test
or evidence that shows it works. Threat categories follow the OWASP Top 10 (2021) where one
applies. Labels match `evidence/architecture.png` (including its two trust-boundary lines) and `src/infra/`.

## Application threats

| # | Threat | Example attack | Control | Implemented in | Verified by |
|---|---|---|---|---|---|
| T1 | **Broken access control / IDOR** (OWASP A01) | Student changes `item_id` in the URL or a hidden form field to read or claim another student's item | Every query on user-owned data includes `reported_by = <session user>`; `claim.php` re-checks ownership of the submitted `lost_item_id` and that the found item is still `open` | `src/public/matches.php`, `claim.php`, `dashboard.php`, `my_claims.php` | S01 to S07 |
| T2 | **Privilege escalation to admin** (A01) | Student opens `admin.php` or forges an approve request | Role checked from the server-side session before any data is read or any POST is handled; role cannot be set at registration (always `student`) | `src/public/admin.php`, `register.php` | S09, S10 |
| T3 | **SQL injection** (A03) | `' OR '1'='1' --` in login or search | Prepared statements with bound parameters for all user input; `intval()` on IDs | All pages in `src/public/` | S13, S14 |
| T4 | **Stored cross-site scripting** (A03) | `<script>` in an item description shown to other users and admins | All user content escaped with `htmlentities()` on output | All pages in `src/public/` | S15 |
| T5 | **Weak or stolen passwords** (A07) | Guessing simple passwords; reading a leaked database | Server-side password rules (8+ chars, upper, lower, number, symbol); bcrypt hashing; same error for wrong email or password | `src/public/register.php`, `login.php` | F02, S12, S16, S17 |
| T6 | **Session fixation / hijacking** (A07) | Attacker plants a session ID before the victim logs in | `session_regenerate_id(true)` on successful login; logout destroys the session | `src/public/login.php`, `logout.php` | F17 (logout) |
| T7 | **Sensitive data exposure** (A02) | Student reads the finder's private detail to fake a claim | `private_detail` is never selected by student-facing queries; only shown in `admin.php` | `src/public/browse.php`, `src/inc/matching.php`, `admin.php` | S11 |
| T8 | **Fraudulent claim** (business logic) | Someone claims an item that isn't theirs | Claim needs a verification answer; Security compares it with the finder's private detail before approving; nothing changes until approval | `claim.php`, `admin.php` | F10 to F16 |
| T9 | **Error messages leaking internals** (A05) | Bad input shows SQL errors, host names or paths | Generic user messages; DB connection errors logged server-side only | `src/inc/db.php`, `matches.php` | S03 |

## Cloud and infrastructure threats

| # | Threat | Control | Implemented in | Verified by |
|---|---|---|---|---|
| T10 | **Credentials committed to Git or the ZIP** | DB login read from the local config file `src/.env` (mode 640, outside the web root), written at boot from the launch template; real values never in Git: `.gitignore`, placeholders in `src/.env.example` and `src/infra/user-data.sh` | `src/inc/db.php`, `src/infra/user-data.sh`, `.gitignore` | S18 (`tests/check_secrets.py`) |
| T11 | **Over-privileged database account** (least privilege) | App connects as `lostfound_app` with only SELECT, INSERT, UPDATE on the 3 tables: no DELETE, DROP, ALTER or GRANT. The RDS master account is only used to load the schema | `data/db_app_user.sql` | `SHOW GRANTS` output in `evidence/deployment.md` |
| T12 | **Database reachable from the internet** (restricted network access) | RDS in private subnets, "Publicly accessible: No"; `db-sg` allows MySQL 3306 **only from `web-sg`** | `src/infra/DEPLOYMENT.md` | `evidence/deployment.md` (redacted SG rules) + connection attempt from a laptop times out |
| T13 | **Web servers attacked directly, bypassing the load balancer** | `web-sg` allows HTTP 80 **only from `alb-sg`**; web servers in private subnets with no public IP; no SSH from 0.0.0.0/0 | `src/infra/DEPLOYMENT.md` | `evidence/deployment.md` (redacted SG rules) |
| T14 | **Over-privileged cloud role** (least privilege) | EC2 instances use the Learner Lab `LabInstanceProfile`; used only by the CloudWatch agent to send logs; the app itself makes no AWS API calls, so no extra permissions are added. Team members use their own Learner Lab accounts, no shared keys | `src/infra/DEPLOYMENT.md` | `evidence/deployment.md` |
| T15 | **Single server failure / overload** (availability) | ALB across 2 AZs with `/health.php` checks; Auto Scaling group (min 1, desired 2, max 3) replaces unhealthy instances | `src/infra/DEPLOYMENT.md` | `evidence/test-resilience.md` |
| T16 | **Attack or fault goes unnoticed; runaway cost** | CloudWatch alarm on unhealthy hosts; Apache/PHP logs in CloudWatch Logs; AWS Budgets cost alert | `src/infra/DEPLOYMENT.md` | `evidence/monitoring.md` |

## Responsible data and AI use

| # | Risk | Control | Where |
|---|---|---|---|
| T17 | Personal data in the dataset | Only synthetic data; no names, emails or student IDs in `data/` | `data/README.md` |
| T18 | Wrong AI suggestion leads to an item going to the wrong person | Suggestions never release an item; a human (Security) approves every claim against the private detail | `admin.php`, `evidence/test-data-ai.md` |

## Accepted risks (documented, not fixed)

No CSRF tokens, no login rate limiting, HTTP only. The database password sits in the launch
template's user data (readable by anyone allowed to view the template); production would use
AWS Secrets Manager with rotation. Reasons and production fixes are in
`evidence/test-security.md` → "Known limitations".
