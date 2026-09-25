"""
Campus Lost & Found - TF-IDF Matching Engine
analytics/match.py

Given a set of LOST reports and FOUND reports, suggests the most likely
counterpart for each lost item, ranked by TF-IDF cosine similarity of the
description fields (category, description, location). Applies a similarity
threshold so that lost items with no real counterpart are not forced into
a false match.

Reproducible offline (no AWS dependency) using the synthetic dataset in
data/ (see data/DATA_DICTIONARY.md and data/generate_dataset.py).
"""

import os
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

DATA_DIR = os.path.join(os.path.dirname(__file__), "..", "data")

# ---------------------------------------------------------------------------
# 1. Load dataset
# ---------------------------------------------------------------------------
lost_items = pd.read_csv(os.path.join(DATA_DIR, "lost_items.csv"))
found_items = pd.read_csv(os.path.join(DATA_DIR, "found_items.csv"))
ground_truth = pd.read_csv(os.path.join(DATA_DIR, "ground_truth.csv"))
# ground_truth.found_id is empty string for lost items with no true match
ground_truth["found_id"] = ground_truth["found_id"].fillna("")

print(f"Loaded {len(lost_items)} lost items and {len(found_items)} found items.")

# ---------------------------------------------------------------------------
# 2. Feature construction: category + description + location -> TF-IDF
# ---------------------------------------------------------------------------
def build_corpus(df):
    return (df["category"] + " " + df["description"] + " " + df["location"]).tolist()

lost_corpus = build_corpus(lost_items)
found_corpus = build_corpus(found_items)

vectorizer = TfidfVectorizer(stop_words="english")
vectorizer.fit(lost_corpus + found_corpus)

lost_vectors = vectorizer.transform(lost_corpus)
found_vectors = vectorizer.transform(found_corpus)

# ---------------------------------------------------------------------------
# 3. Cosine similarity between every lost/found pair
# ---------------------------------------------------------------------------
similarity_matrix = cosine_similarity(lost_vectors, found_vectors)

# ---------------------------------------------------------------------------
# 4. Ranked suggestion per lost item, with a similarity threshold below
#    which the engine reports "no confident match" rather than guessing
# ---------------------------------------------------------------------------
THRESHOLD = 0.30

results = []
for i, lost_row in lost_items.iterrows():
    scores = similarity_matrix[i]
    best_j = scores.argmax()
    best_score = scores[best_j]
    suggested = found_items.iloc[best_j]["id"] if best_score >= THRESHOLD else None
    results.append({
        "lost_id": lost_row["id"],
        "suggested_found_id": suggested,
        "similarity_score": round(float(best_score), 3),
    })

results_df = pd.DataFrame(results)
results_df.to_csv(os.path.join(os.path.dirname(__file__), "..", "evidence", "test-data-ai-output.csv"), index=False)

# ---------------------------------------------------------------------------
# 5. Evaluation against ground_truth.csv
#    - Precision@1 on items that DO have a true match
#    - False-positive rate on items that have NO true match (should stay
#      below threshold and not be suggested)
# ---------------------------------------------------------------------------
gt_map = dict(zip(ground_truth["lost_id"], ground_truth["found_id"]))

has_match = [r for r in results if gt_map.get(r["lost_id"], "") != ""]
no_match = [r for r in results if gt_map.get(r["lost_id"], "") == ""]

correct = sum(1 for r in has_match if r["suggested_found_id"] == gt_map[r["lost_id"]])
precision_at_1 = correct / len(has_match)

false_positives = sum(1 for r in no_match if r["suggested_found_id"] is not None)
false_positive_rate = false_positives / len(no_match)

print(f"\nItems with a true match:    {len(has_match)}")
print(f"Precision@1:                {precision_at_1:.1%}  ({correct}/{len(has_match)} correct)")
print(f"Items with no true match:   {len(no_match)}")
print(f"False positive rate:        {false_positive_rate:.1%}  ({false_positives}/{len(no_match)} wrongly suggested)")
print(f"Similarity threshold used:  {THRESHOLD}")
