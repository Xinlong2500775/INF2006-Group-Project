# Data

Everything in this folder is **synthetic**. There is no real student data, no personal
information and no proprietary data anywhere in the project.

| File | What it is | Used by |
|---|---|---|
| `schema.sql` | MySQL schema for the live app: `users`, `items`, `claims`, plus one demo admin account. | RDS (and local XAMPP) |
| `db_app_user.sql` | Creates the least-privilege `lostfound_app` database account the app connects with. | RDS, run once after `schema.sql` |
| `generate_dataset.py` | Script that generates the evaluation dataset below. | Run manually |
| `lost_items.csv` | 105 synthetic lost-item reports. | `analytics/match.py` |
| `found_items.csv` | 105 synthetic found-item reports. | `analytics/match.py` |
| `ground_truth.csv` | Which found item truly belongs to each lost report (85 pairs, 20 with no match). | `analytics/match.py` (evaluation only) |
| `DATA_DICTIONARY.md` | Every field in every table and CSV. | Reference |

## Provenance of the evaluation dataset

- **Created by:** the team, using `generate_dataset.py` (Python standard library only).
- **How:** for each of 8 everyday categories (Bottle, Keys, Card, Bag, Umbrella, Earphones,
  Jacket, Watch) the script holds a small set of hand-written phrasings and real SIT campus
  location names (e.g. "Library level 2", "W3 canteen"). It builds 85 true lost/found pairs by
  describing the same item in different words, adds 20 lost reports with no counterpart, and
  fills the found side with unrelated items. Records are shuffled.
- **Reproducible:** fixed random seed `42`. Running the script regenerates byte-identical CSVs:
  ```bash
  python3 data/generate_dataset.py
  ```
- **Why synthetic:** the brief forbids personal data, and SIT has no public lost-and-found
  dataset. A generated set also gives us a known ground truth, which real reports would not.
- **Licence:** created by the team for this project; no third-party data.

## Known limitations

- Descriptions come from a small template set, so many reports share wording. This makes the
  matching harder than real life in one way (many near-identical items) and easier in another
  (no typos or slang).
- The evaluation categories (e.g. "Bottle") are simpler than the app's dropdown categories
  (e.g. "Bottle/Container"). The matcher treats both the same way, as plain words.
- Dates are only used as metadata; matching does not use them yet (see `evidence/test-data-ai.md`).

## Loading the app database

```bash
mysql -h <db-host> -u <admin-user> -p -e "CREATE DATABASE IF NOT EXISTS inf2006"
mysql -h <db-host> -u <admin-user> -p inf2006 < data/schema.sql
mysql -h <db-host> -u <admin-user> -p inf2006 < data/db_app_user.sql   # edit the password first
```

The demo admin account in `schema.sql` (`admin@sit.singaporetech.edu.sg` / `admin123`) is for
testing only and must be changed before any real use.
