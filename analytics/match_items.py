#!/usr/bin/env python3
"""
match_items.py — Text-based similarity matching for the Lost & Found Tracker.

Usage:
    python3 match_items.py "<lost item description>" "<found desc 1>|<found desc 2>|..."

Prints a JSON array of {description, score} sorted by similarity score (highest first).
This is called from report_lost.php / matches.php via shell_exec(), passing the lost
item's description and the pool of open found-item descriptions pulled from the database.

Technique: TF-IDF (term frequency-inverse document frequency) converts each description
into a weighted word-frequency vector; cosine similarity then measures how close two
vectors point in the same direction, i.e. how similar the wording/content is.
"""
import sys
import json
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity


def get_matches(lost_description, found_descriptions, top_n=5):
    if not found_descriptions:
        return []

    documents = [lost_description] + found_descriptions
    vectorizer = TfidfVectorizer(stop_words='english')
    tfidf_matrix = vectorizer.fit_transform(documents)

    scores = cosine_similarity(tfidf_matrix[0:1], tfidf_matrix[1:]).flatten()

    ranked = sorted(
        zip(found_descriptions, scores),
        key=lambda pair: pair[1],
        reverse=True
    )

    return [
        {"description": desc, "score": round(float(score), 4)}
        for desc, score in ranked[:top_n]
        if score > 0  # drop zero-similarity items, they're not real matches
    ]


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps([]))
        sys.exit(0)

    lost_description = sys.argv[1]
    found_descriptions = sys.argv[2].split('|') if len(sys.argv) > 2 and sys.argv[2] else []

    results = get_matches(lost_description, found_descriptions)
    print(json.dumps(results))
