# Analytics: text-based lost/found matching

When a student reports a lost item, the app ranks every open found item by how
similar its report is, and shows the top 5 with a match percentage. This helps
the owner find their item without scrolling through every notice, and gives
Security a shortlist instead of manual searching.

| File | Purpose |
|---|---|
| `match_items.py` | The matcher used **live** by the web app (called from `src/inc/matching.php`). |
| `match.py` | Offline evaluation of `match_items.py` against the labelled synthetic dataset. Produces `evidence/test-data-ai-output.csv`. |
| `requirements.txt` | Python dependencies (tested version sets listed inside). |

## Method

1. **Input text:** each report's `category + description + location` joined into one string.
2. **TF-IDF:** each text becomes a vector of word weights. Words that appear in many reports
   (like "black") get less weight than rarer, more informative words (like "Hydro"). English
   stop words are removed.
3. **Cosine similarity:** the lost report is compared with every open found report, giving a
   score from 0 (no shared words) to 1 (same wording).
4. **Output:** the top 5 found items with a score of at least 20% are shown to the student,
   each with its percentage (e.g. "Match score: 72%").

TF-IDF was chosen because it is fast (no GPU, runs on a t3.micro in milliseconds), needs no
training data, and every score can be explained by pointing at the shared words.

## Reproduce the evaluation (no AWS needed)

From the repository root:

```bash
pip install -r analytics/requirements.txt
python3 analytics/match.py
```

Expected output (deterministic, identical on every run):

```
--- PRODUCTION: category + description + location ---
Top-1 accuracy:                  34.1%  (29/85)
Top-3 accuracy:                  65.9%  (56/85)
Top-5 accuracy:                  89.4%  (76/85)
No-match items given a suggestion: 100.0%  (20/20)
```

Full results and interpretation: `evidence/test-data-ai.md`.
Dataset provenance and fields: `data/README.md`, `data/DATA_DICTIONARY.md`.

## Try the matcher on its own

```bash
python3 analytics/match_items.py "Bottle/Container lost my blue Hydro Flask bottle W3" "Bottle/Container blue Hydro Flask water bottle with a dent W3 lobby|Bag grey backpack canteen"
```

Output: `[{"index": 0, "score": 0.7295}]`, where `index` is the position of the found item
in the `|`-separated list. The backpack shares no words with the lost report, so it scores 0
and is left out.

## Limitations

- **Always suggests something.** Same-category items share enough words to pass 20%, so a
  student whose item hasn't been handed in still sees suggestions. Mitigated by human
  verification before release (claims are checked by Security in `admin.php`).
- **Lexical, not semantic.** "Flask" and "bottle" are only linked if they appear together in
  reports. No typo tolerance.
- **Synthetic evaluation data.** Real reports may be messier; results should be re-checked on
  anonymised real data before production use.
- **Photo matching** was considered but not built (needs image storage and embeddings).

## Responsible use

- Suggestions never release an item. A claim must pass Security's manual check against the
  finder's private detail, which students never see.
- The matcher only reads item descriptions, never names, emails or student IDs.
