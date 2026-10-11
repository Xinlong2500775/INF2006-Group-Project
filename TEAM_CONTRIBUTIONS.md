# Team Contributions

**Project:** Campus Lost & Found, INF2006 Team 10

One row per member. "Artefacts / commits" should name real files and, where possible, commit hashes
or GitHub usernames so the marker can trace them (`git log --author="<name>" --oneline`).
Keep each reflection to 1–2 specific sentences.

*(TODO: every member fills in their own row and checks the others. Roles below are only a
suggested split matching the brief's areas; change them to what each person actually did.)*

| Member | Student ID | Role | Artefacts / commits | Test / evidence ownership | Reflection |
|---|---|---|---|---|---|
| Chern De Qian | 2502760 | *e.g. Cloud deployment lead* | *e.g. `src/infra/`, AWS ALB/ASG/RDS setup* | *e.g. `evidence/deployment.md`, `evidence/test-resilience.md`* | |
| Teo Siow Min Glenda | 25018130 | Cloud deployment and operations testing | AWS deployment in the console following `src/infra/DEPLOYMENT.md` (VPC, security groups, RDS, launch template, ALB, Auto Scaling group, CloudWatch alarm); commit `fe7b058` | `evidence/deployment.md`, ALB test run `evidence/test-run-alb-2026-10-10.txt`, Test 4 Parts A and B (`evidence/test-resilience.md`, probe log), alarm history and log query (`evidence/monitoring.md`) | |
| Zhou Xinlong | 2500775 | Backend, security and automated testing | PHP app fixes and hardening (`src/public/`, `src/inc/db.php`, `src/inc/matching.php`), matcher fix and evaluation (`analytics/`), launch script and deployment guide (`src/infra/`), test scripts (`tests/`), repository maintenance | `evidence/test-functional.md`, `evidence/test-security.md`, `evidence/test-data-ai.md`, `evidence/threat-control-map.md`, local test logs | |
| Liew Jia Rong | 2501090 | *e.g. Data / AI lead* | *e.g. `analytics/`, `data/generate_dataset.py`* | *e.g. `evidence/test-data-ai.md`* | |
| Ruan Zhongjiang | 2501780 | *e.g. Frontend and workflows* | *e.g. `src/public/*.php` pages, `style.css`* | *e.g. `evidence/test-functional.md`* | |
| Thiha Thein | 2502266 | *e.g. Monitoring and cost* | *e.g. CloudWatch alarm, report §7* | *e.g. `evidence/monitoring.md`* | |

## Shared work

| Activity | Who |
|---|---|
| Choosing the problem and success criteria | |
| Architecture and service-model decisions | |
| Final review of the report and evidence paths | |

## Team reflection

*(3–5 sentences, agreed by the team: what worked, what you would do differently, e.g. "we should
have checked that the evaluation measured the same code path the app runs much earlier".)*
