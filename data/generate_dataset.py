"""
Campus Lost & Found - Synthetic dataset generator
data/generate_dataset.py

Generates a larger, more realistic synthetic dataset of lost/found item
reports for the TF-IDF matching engine (analytics/match.py), instead of
a handful of hand-typed rows. Deterministic (fixed seed) so the same
files can be regenerated and the reported evaluation numbers reproduced.

Run:
    python3 data/generate_dataset.py
Produces:
    data/lost_items.csv
    data/found_items.csv
    data/ground_truth.csv   (which lost id maps to which found id, if any)
"""

import random
import csv

random.seed(42)

LOCATIONS = [
    "SIT@Punggol Learning Commons", "W3 canteen", "Library level 2",
    "W2 lobby", "Sports hall", "Learning Commons", "Lecture Theatre 1",
    "Staff lounge level 4", "Bus stop outside campus", "Study pod level 3",
]

# Each item type: (category, list of lost-side phrasings, list of found-side phrasings)
ITEM_TYPES = [
    ("Bottle", [
        "black stainless steel water bottle", "blue plastic water bottle",
        "metal thermos flask", "green sports water bottle",
    ], [
        "dark metal water flask", "blue plastic bottle found near study area",
        "steel thermos left on table", "green sports bottle, half full",
    ]),
    ("Keys", [
        "bunch of keys with red lanyard", "house keys on a black keyring",
        "car key with SIT keychain", "set of keys with a whistle attached",
    ], [
        "set of keys, red strap attached", "black keyring with several keys",
        "car key fob, SIT branded keychain", "keys with whistle, found at gate",
    ]),
    ("Card", [
        "SIT student card in blue sleeve", "NRIC card in a wallet",
        "student ID card, no sleeve", "matriculation card, slightly bent",
    ], [
        "student ID card, blue case, name covered", "wallet containing an ID card",
        "SIT card found on floor", "bent matric card near vending machine",
    ]),
    ("Bag", [
        "grey backpack with laptop inside", "black tote bag with files",
        "small sling bag, navy blue", "drawstring bag with gym clothes",
    ], [
        "grey backpack, laptop and charger inside", "black tote bag, papers inside",
        "navy sling bag found on chair", "drawstring bag with sports attire",
    ]),
    ("Umbrella", [
        "black folding umbrella", "clear plastic umbrella",
        "red umbrella with wooden handle",
    ], [
        "black compact umbrella", "clear umbrella left at entrance",
        "red umbrella, wooden handle, slightly wet",
    ]),
    ("Earphones", [
        "white wireless earbuds in case", "black wired earphones",
        "AirPods in a cracked case",
    ], [
        "white earbuds case found on bench", "black wired earphones, tangled",
        "AirPods case, cracked lid",
    ]),
    ("Jacket", [
        "grey hooded jacket", "black windbreaker",
        "denim jacket with pins on collar",
    ], [
        "grey hoodie left on chair", "black windbreaker, size M",
        "denim jacket with badges",
    ]),
    ("Watch", [
        "black digital sports watch", "silver analog wristwatch",
    ], [
        "black sports watch, screen cracked", "silver wristwatch found in restroom",
    ]),
]

N_MATCHED = 85    # lost items that DO have a corresponding found item
N_LOST_ONLY = 20  # lost items with no found counterpart yet
N_FOUND_ONLY = 20 # found items with no claimed owner yet (decoys/distractors)

lost_rows = []
found_rows = []
ground_truth = []

lost_id_counter = 1
found_id_counter = 1

def make_lost(category, desc, loc, date):
    global lost_id_counter
    lid = f"L{lost_id_counter}"
    lost_id_counter += 1
    lost_rows.append({
        "id": lid, "category": category, "description": desc,
        "location": loc, "date_lost": date,
        "reporter_role": random.choice(["student", "staff"]),
    })
    return lid

def make_found(category, desc, loc, date):
    global found_id_counter
    fid = f"F{found_id_counter}"
    found_id_counter += 1
    found_rows.append({
        "id": fid, "category": category, "description": desc,
        "location": loc, "date_found": date,
        "held_by": random.choice(["Security W3", "Security W2", "Security main gate"]),
    })
    return fid

def random_date():
    return f"2026-09-{random.randint(1,28):02d}"

# 1. Matched pairs: same item type, phrased differently by lost vs found reporter,
#    same or nearby location, found date >= lost date
for _ in range(N_MATCHED):
    category, lost_phrases, found_phrases = random.choice(ITEM_TYPES)
    loc = random.choice(LOCATIONS)
    lost_date = random_date()
    lid = make_lost(category, random.choice(lost_phrases), loc, lost_date)
    fid = make_found(category, random.choice(found_phrases), loc, lost_date)
    ground_truth.append({"lost_id": lid, "found_id": fid})

# 2. Lost-only: reported lost, nothing has turned up (no true match should exist)
for _ in range(N_LOST_ONLY):
    category, lost_phrases, _ = random.choice(ITEM_TYPES)
    lid = make_lost(category, random.choice(lost_phrases), random.choice(LOCATIONS), random_date())
    ground_truth.append({"lost_id": lid, "found_id": ""})

# 3. Found-only: decoys / distractor items with no matching lost report in the set,
#    these make the matching task realistic (not every found item has a pending claim)
for _ in range(N_FOUND_ONLY):
    category, _, found_phrases = random.choice(ITEM_TYPES)
    make_found(category, random.choice(found_phrases), random.choice(LOCATIONS), random_date())

random.shuffle(lost_rows)
random.shuffle(found_rows)

with open("/home/claude/project/data/lost_items.csv", "w", newline="") as f:
    writer = csv.DictWriter(f, fieldnames=["id","category","description","location","date_lost","reporter_role"])
    writer.writeheader()
    writer.writerows(lost_rows)

with open("/home/claude/project/data/found_items.csv", "w", newline="") as f:
    writer = csv.DictWriter(f, fieldnames=["id","category","description","location","date_found","held_by"])
    writer.writeheader()
    writer.writerows(found_rows)

with open("/home/claude/project/data/ground_truth.csv", "w", newline="") as f:
    writer = csv.DictWriter(f, fieldnames=["lost_id","found_id"])
    writer.writeheader()
    writer.writerows(ground_truth)

print(f"Generated {len(lost_rows)} lost items, {len(found_rows)} found items, "
      f"{sum(1 for g in ground_truth if g['found_id'])} true matches, "
      f"{sum(1 for g in ground_truth if not g['found_id'])} lost items with no match.")
