# Analytics: Text-based item matching

`match_items.py` compares a lost item's description against all currently open found-item
descriptions using TF-IDF vectorization + cosine similarity, and returns the top matches
ranked by similarity score.

## Run it standalone (for testing/reproducibility evidence)

```bash
pip install -r requirements.txt
python3 match_items.py "Blue Hydro Flask water bottle, dent on side" "Found a blue water bottle near W3 lobby|Grey backpack found near canteen|Black earphones found in LT1"
```

Expected output (JSON, sorted by score):
```json
[{"description": "Found a blue water bottle near W3 lobby", "score": 0.42}]
```

## How it's used in the web app

`src/public/matches.php` calls this script via PHP's `shell_exec()`, passing the logged-in
user's lost-item description and the pipe-separated list of open found-item descriptions
pulled from the `items` table. The script prints JSON to stdout, which PHP decodes and
displays as ranked match cards.

## Limitations

- TF-IDF/cosine similarity is a lexical (word-overlap) technique, not semantic — it won't
  catch synonyms it hasn't seen together in the corpus (e.g. "bottle" vs "flask" only match
  if both words co-occur enough across descriptions to be weighted similarly).
- No spelling-correction or typo tolerance.
- Photo-based matching was considered as an extension but not implemented in this version
  due to the added complexity of image embeddings and sample photo data collection within
  the project timeline.
