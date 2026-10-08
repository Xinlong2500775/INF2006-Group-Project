#!/usr/bin/env python3
"""
Submission preflight (brief, Appendix A). Run from the folder that will be zipped:

    python3 tests/preflight.py

Checks: required files exist, project_manifest.yaml has every required field, every manifest
path exists, no TODO left in the manifest, no forbidden files (real .env, credentials, .git,
caches), and evidence files have no unfilled placeholders. Exit code 0 = ready to zip.
"""
import os
import re
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
os.chdir(ROOT)
problems, warnings = [], []

REQUIRED = ["README.md", "project_manifest.yaml", "report.pdf", "src", "src/.env.example", "data",
            "data/DATA_DICTIONARY.md", "analytics", "analytics/README.md", "evidence", "tests",
            "TEAM_CONTRIBUTIONS.md", "AI_USE_DECLARATION.md"]
for path in REQUIRED:
    if not os.path.exists(path):
        problems.append(f"missing required item: {path}")

SCHEMA = {
    "project": ["group_id", "title", "problem_statement", "cloud_provider", "repository_commit"],
    "team": ["members"],
    "architecture": ["diagram", "service_model", "deployment_model", "components"],
    "evidence": ["functional_test", "security_test", "data_ai_test", "scale_resilience_test", "monitoring"],
    "data_ai": ["dataset", "method", "evaluation", "reproducible_command"],
    "security": ["threat_control_map", "secrets_handling"],
    "run": ["prerequisites", "commands"],
    "declarations": ["ai_use", "contributions"],
}
try:
    import yaml
    with open("project_manifest.yaml", encoding="utf-8") as fh:
        raw = fh.read()
    manifest = yaml.safe_load(raw)
    for section, fields in SCHEMA.items():
        for field in fields:
            if field not in (manifest.get(section) or {}):
                problems.append(f"manifest: missing {section}.{field}")
    for m in manifest["team"]["members"]:
        for field in ("name", "student_id", "role"):
            if not str(m.get(field, "")).strip() or "TODO" in str(m.get(field)):
                problems.append(f"manifest: member {m.get('name')} has no {field}")

    def strings(o):
        if isinstance(o, dict):
            for v in o.values():
                yield from strings(v)
        elif isinstance(o, list):
            for v in o:
                yield from strings(v)
        elif isinstance(o, str):
            yield o

    for s in strings(manifest):
        for p in re.findall(r"(?<![\w/.-])((?:src|data|analytics|evidence|tests)/[\w./-]+\.\w+|[A-Z_]+\.md)", s):
            if not os.path.exists(p):
                problems.append(f"manifest path does not exist: {p}")
    if "TODO" in raw:
        problems.append("manifest still contains TODO")
except ImportError:
    warnings.append("PyYAML not installed (pip install pyyaml); manifest not validated")
except FileNotFoundError:
    pass

for dirpath, dirnames, filenames in os.walk("."):
    for d in list(dirnames):
        if d in (".git", "__pycache__", ".venv", "output") and not (d == "output" and dirpath != "./tests"):
            problems.append(f"remove before zipping: {os.path.join(dirpath, d)}")
            dirnames.remove(d)
    for f in filenames:
        rel = os.path.join(dirpath, f)[2:]
        if f in (".env", "dbinfo.inc.php") or f.endswith((".pem", ".ppk")):
            problems.append(f"remove before zipping (credentials): {rel}")
        if rel.startswith("evidence/") and f.endswith(".md"):
            text = open(rel, encoding="utf-8").read()
            if re.search(r"YYYY-MM-DD|\(paste |\*\(fill in\)\*|MET / NOT MET", text):
                warnings.append(f"unfilled placeholder in {rel}")

if os.path.exists("report.pdf"):
    try:
        import subprocess
        out = subprocess.run(["pdfinfo", "report.pdf"], capture_output=True, text=True).stdout
        pages = int(re.search(r"Pages:\s+(\d+)", out).group(1))
        if not 8 <= pages <= 12:
            warnings.append(f"report.pdf has {pages} pages (8-12 excluding references/appendices)")
    except Exception:
        warnings.append("could not count report.pdf pages (pdfinfo not available); check manually")

for w in warnings:
    print("WARN ", w)
for p in problems:
    print("FAIL ", p)
print(f"\n{len(problems)} problem(s), {len(warnings)} warning(s)")
sys.exit(1 if problems else 0)
