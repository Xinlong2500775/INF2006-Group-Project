# Team Contributions

**Project:** Campus Lost & Found, INF2006 Team 10 (G010)

One row per member. Each member owned one area of the brief, the artefacts and evidence files in
their row, and the matching report section(s). Commits can be traced with
`git log --author="<name>" --oneline`.

| Member | Student ID | Role | Artefacts / commits | Test / evidence ownership | Reflection |
|---|---|---|---|---|---|
| Teo Siow Min Glenda | 2501813 | Cloud Infrastructure & Deployment | AWS deployment in the console following `src/infra/DEPLOYMENT.md`: VPC (public, app and DB subnets across 2 AZs), NAT gateway, security groups, RDS `lostfound-db`, launch template, ALB `lostfound-alb`, Auto Scaling group `lostfound-asg`, CloudWatch alarm `lostfound-unhealthy-alarm`, log group `/lostfound/httpd`, AWS Budgets; commit `fe7b058` | `evidence/deployment.md`; ALB test run `evidence/test-run-alb-2026-10-10.txt`; Test 4 Parts A and B (`evidence/test-resilience.md`, `evidence/test-resilience-log-2026-10-11.txt`) | Deploying the full stack showed me how many settings must line up (subnets, security groups, health checks, scaling policy) before a test is meaningful. Next time I would bake a custom AMI and set up monitoring before testing, not during it. |
| Zhou Xinlong | 2500775 | Backend / Application Developer | PHP application and hardening (`src/public/`, `src/inc/auth.php`, `src/inc/db.php`): prepared statements, session regeneration, server-side password rules, `health.php`; launch script and deployment guide (`src/infra/`); repository maintenance | `evidence/test-functional.md`; automated test suite `tests/run_tests.py`; local test run `evidence/test-run-local-2026-10-08.txt` | |
| Chern De Qian | 2502760 | Data & AI/ML Lead | Synthetic dataset and generator (`data/generate_dataset.py`, `data/*.csv`, fixed seed 42), `data/DATA_DICTIONARY.md`, `data/README.md`; TF-IDF matcher and evaluation (`analytics/match_items.py`, `analytics/match.py`, `src/inc/matching.php`); report §5 | `evidence/test-data-ai.md`, `evidence/test-data-ai-output.csv` (Top-5 89.4%, threshold sweep) | |
| Ruan Zhongjiang | 2501780 | Security Lead | Threat-control map (T1–T18, OWASP Top 10); least-privilege DB account `data/db_app_user.sql`; secrets handling (`src/.env.example`, `.gitignore`, `tests/check_secrets.py`, `tests/check_password_hashes.sql`); report §4.3 | `evidence/test-security.md`, `evidence/threat-control-map.md`, `evidence/s17-password-hashes-local-2026-10-08.txt`, `evidence/s18-secrets-scan-2026-10-08.txt` | |
| Thiha Thein | 2502266 | Scalability, Resilience & Monitoring | Auto Scaling policy design (target tracking, 50% CPU), health-check settings, CloudWatch monitoring plan (metrics, alarm, 7-day log retention); load test script `tests/load_test.py`; cost estimate and sustainability; report §6–7 | `evidence/monitoring.md` (alarm history, Logs Insights query and interpretation) | |
| Liew Jia Rong | 2501090 | Architecture, Documentation & Integration | Architecture diagram `evidence/architecture.png`; `README.md`, `project_manifest.yaml`, `AI_USE_DECLARATION.md`, this file; report §1–2 and §8, final editing and ZIP packaging | Submission preflight (brief Appendix A): every manifest path checked from the ZIP root, redaction check of all evidence files | |

## Shared work

| Activity | Who |
|---|---|
| Choosing the problem, users and success criteria | All members |
| Architecture and service-model decisions | All members |
| Weekly progress check and task tracking | All members |
| Final review of the report and evidence paths | All members |

## Team reflection

One thing we learned from this project is that it is better to test and document things as we go instead of leaving them until the end. Some parts of the system changed during development, so we had to go back and make sure the code, tests, report and AWS setup were still describing the same thing. We also realised that testing should be done on the actual version of the application that users will use, not just on separate scripts or local results. If we did the project again, we would deploy to AWS earlier and collect the testing and monitoring evidence throughout the project instead of doing most of it near the end. Overall, the project helped us understand how the application, database, matching feature and AWS services all need to work together rather than being treated as separate parts.
