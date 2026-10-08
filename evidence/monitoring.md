# Monitoring and Logging

| Field | Value |
|---|---|
| **Objective** | Show the team can spot problems: what is monitored, one alarm that was triggered on purpose, and one log query with its interpretation. |
| **Date** | `YYYY-MM-DD` *(fill in)* |
| **Artefacts** | Alarm history export (below), `evidence/test-resilience.md`, Apache logs excerpt (below, sensitive fields removed) |

## What is monitored

| Signal | Source | Why it matters | Alert |
|---|---|---|---|
| `UnHealthyHostCount` (lostfound-tg) | ALB → CloudWatch, automatic | A web server is down or can't reach the database (`/health.php` returns 503) | **Alarm `lostfound-unhealthy-hosts`**: ≥ 1 for 1 minute |
| `HTTPCode_Target_5XX_Count` | ALB → CloudWatch, automatic | App errors seen by users | Watched on the dashboard |
| `TargetResponseTime` | ALB → CloudWatch, automatic | Slow pages (e.g. matching under load) | Watched |
| `CPUUtilization` (lostfound-asg) | EC2 → CloudWatch, automatic | Drives the scale-out policy (target 50%) | Auto Scaling policy |
| `CPUUtilization`, `FreeStorageSpace` (lostfound-db) | RDS → CloudWatch, automatic | Database overload or running out of space | Watched |
| Apache access/error logs | CloudWatch Logs `/lostfound/httpd` (7-day retention), shipped by the CloudWatch agent from each instance | Request-level detail: which page failed, failed logins, 503s from `health.php` | Queried below |
| PHP error log | Same log group, stream `<instance>/php-error` | Real DB connection errors (hidden from users, see `src/inc/db.php`) | Queried below |

## Operational test: alarm triggered on purpose

Performed as part of Test 4 (`evidence/test-resilience.md`): one web server was stopped and the
alarm moved OK → ALARM → OK.

```
(paste: aws cloudwatch describe-alarm-history --alarm-name lostfound-unhealthy-hosts --history-item-type StateUpdate --max-records 5 --query "AlarmHistoryItems[].[Timestamp,HistorySummary]" --output text)
```

**Interpretation:** *(e.g. "The alarm fired 1 min 40 s after the instance was stopped, inside
the 5-minute target, and cleared once the Auto Scaling replacement passed its health checks.")*

## Log query

**Option A (preferred): CloudWatch Logs Insights** on log group `/lostfound/httpd`, last 1 hour.
Counts requests per page and status, after running the automated tests against the ALB:

```
fields @message
| filter @logStream like /access/
| parse @message /"(?<method>\w+) (?<path>[^ ?]+)[^"]*" (?<status>\d{3})/
| stats count(*) as requests by path, status
| sort requests desc
| limit 20
```

Export: Logs Insights → Export results → Copy as markdown, and paste below.

**Option B (if the CloudWatch agent was blocked):** on a web server (EC2 Instance Connect):

```bash
sudo awk '{print $9}' /var/log/httpd/access_log | sort | uniq -c | sort -rn
sudo awk '{print $7}' /var/log/httpd/access_log | cut -d'?' -f1 | sort | uniq -c | sort -rn | head
sudo grep -c "health.php: database check failed" /var/log/httpd/error_log /var/log/php-fpm/www-error.log 2>/dev/null
```

```
(paste output here; remove client IP addresses before committing)
```

**Interpretation:** *(e.g. "Most requests are ALB health checks to /health.php (all 200). The 302s
are redirects to login.php from anonymous requests, as expected. No 5xx in the period, and no
database check failures.")*

## Limitations

- 7-day log retention keeps cost low but limits investigation of older incidents.
- If the CloudWatch agent is blocked (Option B), logs only live on each instance and are lost when
  the Auto Scaling group replaces it.
- The alarm has no notification target in the Learner Lab; in production it would notify the
  team through SNS (email or chat).
