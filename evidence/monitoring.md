# Monitoring and Logging

| Field | Value |
|---|---|
| **Objective** | Show the team can spot problems: what is monitored, one alarm that was triggered on purpose, and one log query with its interpretation. |
| **Date** | 2026-10-11 (during Test 4) |
| **Artefacts** | Alarm history export (below), `evidence/test-resilience.md`, Apache logs excerpt (below, sensitive fields removed) |

## What is monitored

| Signal | Source | Why it matters | Alert |
|---|---|---|---|
| `UnHealthyHostCount` (lostfound-tg) | ALB → CloudWatch, automatic | A web server is down or can't reach the database (`/health.php` returns 503) | **Alarm `lostfound-unhealthy-alarm`**: ≥ 1 for 1 minute |
| `HTTPCode_Target_5XX_Count` | ALB → CloudWatch, automatic | App errors seen by users | Watched on the dashboard |
| `TargetResponseTime` | ALB → CloudWatch, automatic | Slow pages (e.g. matching under load) | Watched |
| `CPUUtilization` (lostfound-asg) | EC2 → CloudWatch, automatic (5-minute basic monitoring) | Drives the scale-out policy (target 50%) | Auto Scaling policy alarm `TargetTracking-lostfound-asg-AlarmHigh` |
| `CPUUtilization`, `FreeStorageSpace` (lostfound-db) | RDS → CloudWatch, automatic | Database overload or running out of space | Watched |
| Apache access/error logs | CloudWatch Logs `/lostfound/httpd` (7-day retention), shipped by the CloudWatch agent from each instance | Request-level detail: which page failed, failed logins, 503s from `health.php` | Queried below |
| PHP error log | Same log group, stream `<instance>/php-error` | Real DB connection errors (hidden from users, see `src/inc/db.php`) | Queried below |

## Operational test: alarm triggered on purpose

Performed as part of Test 4 Part A (`evidence/test-resilience.md`): one web server was stopped at
about 17:30:20 SGT. Alarm history of `lostfound-unhealthy-alarm` (condition `UnHealthyHostCount` ≥ 1
for 1 datapoint within 1 minute), from the CloudWatch console History tab, times in SGT:

| Time | State change | Cause |
|---|---|---|
| 17:34:37 | OK → In alarm | Instance stopped on purpose (Part A) |
| 17:35:37 | In alarm → OK | Replacement healthy, target group back to 2 healthy |
| 18:22:37 | OK → In alarm | Load test, 60 threads (18:19–18:29) |
| 18:23:37 | In alarm → OK | |
| 18:24:37 | OK → In alarm | Load test, 60 threads |
| 18:25:37 | In alarm → OK | |
| 18:53:37 | OK → In alarm | Just after the 30-thread scaling run (18:43–18:51:49) |
| 18:54:37 | In alarm → OK | |

The scaling alarm `TargetTracking-lostfound-asg-AlarmHigh` (average CPU > 20% during Part B) went
OK → In alarm at 18:49:33 and executed the scaling policy in the same second; the Auto Scaling
group launched the third instance 12 s later (18:49:45). It returned to OK at 18:54:33.

**Interpretation:** the unhealthy-host alarm caught the deliberate fault about 4 minutes after the
instance was stopped, inside our 5-minute target, and cleared by itself once the Auto Scaling
replacement was healthy, so detection and recovery both worked without anyone stepping in. It also
fired three times for one minute during the load tests. Nothing was stopped then: busy servers
answered the health check too slowly and were briefly counted as unhealthy. This is a useful
warning, because under heavy load the load balancer could take working servers out of service. It
points to the same fix as Test 4B: move the matching work out of the request.

## Log query

CloudWatch Logs Insights on log group `/lostfound/httpd`, 2026-10-11 17:09–19:09 SGT (covers Test 4
Parts A and B). 105,452 records scanned (14.3 MB). Requests per page and status code:

```
fields @message
| filter @logStream like /access/
| parse @message /"(?<method>\w+) (?<path>[^ ?]+)[^"]*" (?<status>\d{3})/
| stats count(*) as requests by path, status
| sort requests desc
| limit 20
```

| Path | Status | Requests |
|---|---|---|
| `/login.php` | 200 | 51,040 |
| `/matches.php` | 302 | 50,761 |
| `/health.php` | 200 | 1,970 |
| `/matches.php` | 200 | 1,452 |
| `/matches.php` | 504 | 85 |
| `/` | 200 | 8 |
| `/login.php` | 302 | 4 |
| `/dashboard.php` | 200 | 4 |
| `/report_lost.php` | 302 | 4 |
| `/register.php` | 302 | 4 |
| `/.env` | 404 | 2 |
| `/favicon.ico` | 404 | 2 |
| `/style.css` | 200 | 1 |

**Interpretation:**

- **Most of the first load run never reached the matcher.** 50,761 requests to `/matches.php` were
  redirected (302) to the login page, and `/login.php` was served 51,040 times. These come from the
  60-thread run (18:19–18:29), in which the load script's logins failed, so it mostly measured cheap
  redirects. Access control still held: anonymous requests never saw any matches.
- **Real matching is expensive.** Only 1,452 `/matches.php` requests returned 200 (actually ran
  TF-IDF), yet they were enough to push average CPU to about 30% with response times of about 29 s.
- **85 × 504 Gateway Timeout on `/matches.php`.** The load balancer gave up waiting for a server
  that was still busy with matching. This matches the client-side timeouts in Test 4B and supports
  moving matching out of the request (report Section 3.3).
- **Health checks returned 200 every time they completed** (1,970). The short unhealthy-host alarms
  during the load runs came from checks that were too slow, not from failed database checks.
- **Scanning for secrets.** 2 requests for `/.env` returned 404. This is typical automated scanning.
  The web root is `src/public/`, and the real `src/.env` (written at boot by the launch template) sits
  outside it, so nothing was exposed.
- No 5xx errors on any other page in this period.

## Limitations

- 7-day log retention keeps cost low but limits investigation of older incidents.
- If the CloudWatch agent is blocked (Option B), logs only live on each instance and are lost when
  the Auto Scaling group replaces it.
- The alarm has no notification target in the Learner Lab; in production it would notify the
  team through SNS (email or chat).
