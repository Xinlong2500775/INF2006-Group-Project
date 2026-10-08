#!/usr/bin/env python3
"""
match_items.py: text-based similarity matching for the Campus Lost & Found app.

Usage:
    python3 match_items.py "<lost item text>" "<found text 1>|<found text 2>|..."

Each "text" is the item's category, description and location joined into one
string (built by src/inc/matching.php, and by build_text() below for offline
evaluation), e.g. "Bottle/Container blue Hydro Flask bottle with a dent W3 lobby".

Prints a JSON array of {"index", "score"} for the top matches, highest score
first. "index" is the 0-based position of the found item in the input list, so
the caller can map each score back to the exact found item even when two found
items have identical wording.

Technique: TF-IDF (term frequency-inverse document frequency) turns each text
into a weighted word-frequency vector; cosine similarity then measures how close
two vectors point in the same direction, i.e. how much meaningful wording two
reports share. Common English stop words are removed first.

This exact function is also what analytics/match.py evaluates, so the evaluation
results describe the production code path, not a separate prototype.
"""
import sys
import json
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

TOP_N = 5          # number of suggestions shown to the student
MIN_SCORE = 0.20   # suggestions below 20% are hidden (also enforced in matches.php)


def build_text(category, description, location):
    """Combine the fields used for matching into one string."""
    return f"{category} {description} {location}"


def get_matches(lost_text, found_texts, top_n=TOP_N):
    """Return [{"index": i, "score": s}, ...] for the top_n most similar found texts."""
    if not found_texts or not lost_text.strip():
        return []

    documents = [lost_text] + found_texts
    try:
        tfidf_matrix = TfidfVectorizer(stop_words="english").fit_transform(documents)
    except ValueError:
        # Every word was a stop word (e.g. "it"), so there is nothing to compare.
        return []

    scores = cosine_similarity(tfidf_matrix[0:1], tfidf_matrix[1:]).flatten()

    # Stable sort: ties keep their original order, so results are deterministic.
    ranked = sorted(range(len(scores)), key=lambda i: scores[i], reverse=True)

    return [
        {"index": i, "score": round(float(scores[i]), 4)}
        for i in ranked[:top_n]
        if scores[i] > 0  # zero similarity means no shared words at all
    ]


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps([]))
        sys.exit(0)

    lost_text = sys.argv[1]
    found_texts = sys.argv[2].split("|") if len(sys.argv) > 2 and sys.argv[2] else []

    print(json.dumps(get_matches(lost_text, found_texts)))
