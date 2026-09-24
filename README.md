# Lost & Found Tracker — INF2006 Team Project 

A web app for reporting, browsing, and matching lost/found campus items, with automatic
text-based match suggestions powered by TF-IDF + cosine similarity.

## Problem statement

SIT's current lost & found process (physically handing items to Security, or emailing to
check on a lost item) has no online visibility, no searchable inventory, and no way to
proactively match lost reports against found items. This app lets students report lost or
found items online, browse found items, and get automatically ranked possible matches.

## Structure

```
src/
  public/       — PHP pages (login, register, report forms, browse, matches, claim)
  inc/          — shared PHP includes (db connection, auth helpers)
analytics/
  match_items.py    — TF-IDF/cosine similarity matching script
  requirements.txt
  README.md         — how the matching feature works, reproducibility notes
data/
  schema.sql    — database schema (users, items, claims)
```

## Quickstart (deploying to EC2, same pattern as Lab 4)

1. Launch an EC2 instance, install httpd + PHP + MySQL client (see Lab 4 steps)
2. Install Python 3 + `pip install -r analytics/requirements.txt` on the same instance
3. Create an RDS MySQL database, run `data/schema.sql` against it to create the tables
4. Copy `src/inc/dbinfo.inc.php.example` to `src/inc/dbinfo.inc.php` and fill in your
   real RDS endpoint/credentials (keep this file OUT of your submission/git repo)
5. Copy `src/public/*` and `src/inc/*` to `/var/www/html/` on the EC2 instance
   (keep `inc/` one level outside the web-servable folder if possible, same principle as Lab 4)
6. Copy `analytics/` to the EC2 instance too, `matches.php` calls it via `shell_exec()`
   using a relative path, so keep the folder structure intact
7. Visit `http://<ec2-public-ip>/register.php` to create your first account

## Technology choices

- **PHP** for the web app (team familiarity, consistent with Lab 4 patterns)
- **MySQL on RDS** for persistent storage (users, items, claims)
- **Python (scikit-learn)** for the analytics/AI feature, called from PHP via `shell_exec()`
- Passwords are hashed with `password_hash()` (bcrypt), never stored in plain text
- All user input is escaped (`mysqli_real_escape_string`, prepared statements) against SQL
  injection, and output-escaped (`htmlentities`) against XSS, same patterns as Lab 4

## What's built so far

- User registration/login (bcrypt password hashing)
- Report lost item / report found item
- Browse open found items
- Automatic match suggestions (TF-IDF + cosine similarity) after reporting a lost item
- Claim flow (student claims a found item)

## Not yet built (next steps)

- Admin panel (approve/reject claims, mark items returned/discarded)
- Deployment to EC2 + ALB + Auto Scaling Group (Lab 3 pattern)
- CloudWatch monitoring/alarms
- Security test, scalability test, data/AI validation test (required evidence)
- Photo upload (optional stretch feature, would need S3)
=======
# INF2006-Group-Project
Lost and Found Webpage

