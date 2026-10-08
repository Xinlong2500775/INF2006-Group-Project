"""
Campus Lost & Found: offline evaluation of the TF-IDF matching feature
analytics/match.py

Evaluates the SAME function the web app uses (match_items.get_matches), with
the SAME settings the app uses (category + description + location text, top 5
suggestions, 20% minimum score), against the labelled synthetic dataset in
data/ (see data/README.md and data/DATA_DICTIONARY.md).

For every lost report, the script asks the matcher for suggestions out of all
105 found reports, then checks where the true found item (from
data/ground_truth.csv) appears in the list the student would see.

Metrics (success criterion in report section 1.2: Top-5 accuracy >= 50%):
  - Top-1 / Top-3 / Top-5 accuracy on the 85 lost items that have a true match
  - Suggestion rate on the 20 lost items with NO true match (how often the app
    still shows something; lower is better)

A baseline using description only (the app's matching before 2026-10-08) is
reported alongside, to show the effect of adding category and location.

Usage (from the repository root):
    pip install -r analytics/requirements.txt
    python3 analytics/match.py

Writes per-item results to evidence/test-data-ai-output.csv. Fully
deterministic: no randomness, same output on every run.
"""

import os
import sys

import pandas as pd

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from match_items import get_matches, build_text, TOP_N, MIN_SCORE  # noqa: E402

DATA_DIR = os.path.join(HERE, "..", "data")
OUTPUT_CSV = os.path.join(HERE, "..", "evidence", "test-data-ai-output.csv")

# ---------------------------------------------------------------------------
# 1. Load dataset
# ---------------------------------------------------------------------------
lost_items = pd.read_csv(os.path.join(DATA_DIR, "lost_items.csv"))
found_items = pd.read_csv(os.path.join(DATA_DIR, "found_items.csv"))
ground_truth = pd.read_csv(os.path.join(DATA_DIR, "ground_truth.csv"))
ground_truth["found_id"] = ground_truth["found_id"].fillna("")  # "" = no true match
gt_map = dict(zip(ground_truth["lost_id"], ground_truth["found_id"]))

print(f"Loaded {len(lost_items)} lost items and {len(found_items)} found items.")
print(f"Settings: top {TOP_N} suggestions, minimum score {MIN_SCORE:.0%}\n")


# ---------------------------------------------------------------------------
# 2. Run the matcher exactly as the app does
# ---------------------------------------------------------------------------
def suggestions_for(lost_row, text_fn):
    found_texts = [text_fn(f) for _, f in found_items.iterrows()]
    results = get_matches(text_fn(lost_row), found_texts)
    shown = [r for r in results if r["score"] >= MIN_SCORE]  # same filter as matches.php
    return [(found_items.iloc[r["index"]]["id"], r["score"]) for r in shown]


def full_text(row):
    return build_text(row["category"], row["description"], row["location"])


def description_only(row):
    return row["description"]


def evaluate(text_fn):
    rows = []
    for _, lost in lost_items.iterrows():
        shown = suggestions_for(lost, text_fn)
        true_id = gt_map.get(lost["id"], "")
        shown_ids = [fid for fid, _ in shown]
        rank = shown_ids.index(true_id) + 1 if true_id and true_id in shown_ids else None
        rows.append({
            "lost_id": lost["id"],
            "true_found_id": true_id or "(none)",
            "rank_of_true_match": rank if rank else "",
            "suggestions_shown": len(shown),
            "top_suggestion": shown_ids[0] if shown else "",
            "top_score": shown[0][1] if shown else "",
            "all_suggestions": "; ".join(f"{fid}={score:.2f}" for fid, score in shown),
        })
    return pd.DataFrame(rows)


def summarise(df, label):
    has_match = df[df["true_found_id"] != "(none)"]
    no_match = df[df["true_found_id"] == "(none)"]
    n = len(has_match)
    print(f"--- {label} ---")
    for k in (1, 3, 5):
        hits = sum(1 for r in has_match["rank_of_true_match"] if r != "" and int(r) <= k)
        print(f"Top-{k} accuracy:                 {hits / n:6.1%}  ({hits}/{n})")
    shown = (no_match["suggestions_shown"] > 0).sum()
    print(f"No-match items given a suggestion: {shown / len(no_match):6.1%}  ({shown}/{len(no_match)})")
    print(f"Average suggestions shown:         {df['suggestions_shown'].mean():.2f}\n")


# ---------------------------------------------------------------------------
# 3. Evaluate the production setting and the old baseline
# ---------------------------------------------------------------------------
production = evaluate(full_text)
baseline = evaluate(description_only)

summarise(production, "PRODUCTION: category + description + location")
summarise(baseline, "BASELINE (before 2026-10-08): description only")

production.to_csv(OUTPUT_CSV, index=False)
print(f"Per-item results written to {os.path.relpath(OUTPUT_CSV)}")
