# AI Use Declaration

**Project:** Campus Lost & Found, INF2006 Team 10

This declaration follows the module's academic integrity policy. All AI-assisted output was
reviewed, modified where needed, and verified by the team before inclusion. Nothing AI-generated is
presented as unverified fact: every technical claim in the report points to code, a test or an
evidence file that the team ran.

## 1. AI tools used

| Tool | Provider | Used by | Used for |
|---|---|---|---|
| Claude | Anthropic | Team 10 members | Explanations, code suggestions and review, test scripts, documentation drafts (details below) |

No other AI tools (e.g. ChatGPT, GitHub Copilot) were used. *(TODO: each member confirms this is
true for them, or adds the tool they used to this table.)*

## 2. Where AI was used

| Area / file | How AI was used | Team decisions, changes and verification |
|---|---|---|
| PHP web app (`src/public/`, `src/inc/`) | Suggested page structure and input-validation patterns, explained prepared statements and `htmlentities()` | Team designed the lost/found/claim workflow and the human-verified claim process (`private_detail` + `verification_answer`), wrote and tested the pages, added server-side password rules in `register.php` |
| Login hardening (`login.php`) | Suggested switching to a prepared statement and calling `session_regenerate_id()` on login | Applied by the team; covered by tests F04, F05, S12, S13 |
| Configuration and secrets (`src/inc/db.php`, `src/.env.example`, `src/public/health.php`) | Generated the environment-variable config loader (backward compatible with `dbinfo.inc.php`) and the database health-check page | Team checked that the existing local setup still works and that secrets stay out of Git (`tests/check_secrets.py`); health check tested with working and broken DB credentials |
| TF-IDF matching (`analytics/`) | Explained TF-IDF and cosine similarity. During a review on 2026-10-08, identified that the live app matched on description only while the evaluation used category + description + location, and that scores were mapped back by description text (wrong item when descriptions repeat). Generated the fixed `match_items.py`, `matching.php` and an evaluation script that tests the production function | Team chose text matching over photo matching, chose the 20% threshold and Top-5 display, reviewed the fix, and re-ran `python3 analytics/match.py` to confirm the reported numbers (Top-5 89.4%). The failed earlier result is kept in `evidence/test-data-ai.md` |
| Dataset (`data/generate_dataset.py`) | Helped write the generator; fixed a hard-coded file path that stopped it running on other machines | Team defined categories, campus locations and the 85/20 split; regeneration verified to produce byte-identical CSVs |
| Automated tests (`tests/`) | Generated `run_tests.py`, `check_secrets.py` and `check_password_hashes.sql` from the test plan | Team ran them locally and against the ALB; the logs in `evidence/` are from the team's own runs. A deliberate bug was injected to confirm the tests detect failures (`tests/README.md`) |
| AWS deployment (`src/infra/`) | Drafted the launch script and deployment guide (security groups, SSM parameters, stickiness, alarm) and helped troubleshoot console steps that differed from the lab sheets | All AWS resources were created, configured and checked by the team in the AWS console; `evidence/deployment.md` records the actual configuration |
| Architecture diagram (`evidence/architecture.svg/.png`) | Drew the diagram from the team's architecture description | Team checked every label against the deployed resource names |
| Security analysis (`evidence/threat-control-map.md`) | Suggested threat categories (OWASP Top 10) and mapped them to controls | Team confirmed each control exists in the code/config and is covered by a test |
| Documentation (`README.md`, `data/README.md`, `analytics/README.md`, evidence templates, `project_manifest.yaml`, this file) | Drafted structure and text from the project brief and the team's artefacts | Team filled in results, checked every fact against the artefacts and evidence files |
| `report.pdf` | Review for consistency with the code and evidence, grammar and clarity suggestions | Analysis, results, decisions and conclusions are the team's own |

## 3. Sources, baselines and licences

### Baselines and references

- INF2006 lab materials: Lab 4/Lab 5 PHP + RDS pattern (connection include file kept outside the
  web root, `mysqli`, EC2 + RDS in a VPC), and the load balancing / auto scaling lab steps.
- AWS documentation: EC2 Auto Scaling, Elastic Load Balancing, RDS, Systems Manager Parameter
  Store, CloudWatch (alarms, agent, Logs Insights).
- OWASP Top 10 (2021) for threat categories.
- scikit-learn documentation for `TfidfVectorizer` and `cosine_similarity`.

No third-party application code, templates or open-source project was used as a starting point.
*(TODO: add any tutorial or snippet a member copied from, with URL and licence.)*

### Open-source software

| Software | Purpose | Licence |
|---|---|---|
| PHP 8 | Web application language | PHP License 3.01 |
| Apache HTTP Server | Web server | Apache-2.0 |
| MySQL (Amazon RDS) / MariaDB (local) | Database | GPL-2.0 (used as a service, not redistributed) |
| scikit-learn | TF-IDF vectoriser, cosine similarity | BSD-3-Clause |
| pandas | Loading CSVs in the evaluation script | BSD-3-Clause |
| NumPy | Numerical arrays (scikit-learn dependency) | BSD-3-Clause |
| requests | HTTP client for the automated tests | Apache-2.0 |
| Amazon CloudWatch agent | Ships logs to CloudWatch | MIT |

Versions: `analytics/requirements.txt`, `tests/requirements.txt`.

### Data

All data in `data/` is synthetic, generated by the team with `data/generate_dataset.py` (fixed seed
42). It contains no personal, sensitive or proprietary data. Provenance: `data/README.md`.

## 4. Verification performed

- **Code:** every AI-suggested change was read and understood by the member responsible, and
  tested before merging. The automated suite (`tests/run_tests.py`, 33 checks) passes against the
  local and deployed app; see `evidence/test-functional.md` and `evidence/test-security.md`.
- **Security:** controls are only claimed where implemented and tested
  (`evidence/threat-control-map.md` → `evidence/test-security.md`).
- **AI/ML feature:** results come from running `analytics/match.py` on labelled data, not from AI
  output; the script is deterministic and the CSV is byte-identical on re-runs.
- **Cloud configuration:** console steps suggested by AI were checked against the live console and
  the INF2006 lab sheets; the real configuration is exported in `evidence/deployment.md`.
- **Documentation:** facts in the report, README and manifest were checked against the artefacts
  and evidence files.

## 5. Team statement

We confirm that this declaration is accurate and complete. The team takes responsibility for all
submitted work, including any parts produced with AI assistance.
