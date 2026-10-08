# Test 4: Scalability and Resilience Test

| Field | Value |
|---|---|
| **Objective** | Show that the service keeps working when a web server fails, and that the failure is detected and repaired automatically (report §1.2: CloudWatch alarm goes off within 5 minutes of a fault caused on purpose, e.g. stopping an EC2 instance). |
| **Mechanisms tested** | ALB health checks on `/health.php` across 2 AZs; Auto Scaling group `lostfound-asg` (min 2) replacing unhealthy instances; CloudWatch alarm `lostfound-unhealthy-hosts`. |
| **Date** | `YYYY-MM-DD` *(fill in)* |
| **Setup** | Deployed as in `src/infra/DEPLOYMENT.md`. Both targets in `lostfound-tg` healthy. Alarm in OK state. |
| **Artefacts** | `evidence/test-resilience-log-YYYY-MM-DD.txt` (availability probe output), CloudWatch alarm history (exported text or one redacted screenshot), `evidence/deployment.md` |

## Steps

1. **Start an availability probe** (laptop or CloudShell). It requests the site every 5 seconds
   and records the HTTP status, so we can see whether users were affected:

   ```bash
   # Bash / CloudShell
   while true; do echo "$(date +%T) $(curl -s -o /dev/null -w '%{http_code}' --max-time 5 http://<alb-dns-name>/health.php)"; sleep 5; done | tee evidence/test-resilience-log-YYYY-MM-DD.txt
   ```
   ```powershell
   # Windows PowerShell
   while ($true) { try { $c = (Invoke-WebRequest "http://<alb-dns-name>/health.php" -UseBasicParsing -TimeoutSec 5).StatusCode } catch { $c = "ERR" }; "$(Get-Date -Format HH:mm:ss) $c" | Tee-Object -Append evidence\test-resilience-log-YYYY-MM-DD.txt; Start-Sleep 5 }
   ```

2. **Record the start time T0**, then cause the fault: EC2 → Instances → select one
   `lostfound-asg` instance → **Instance state → Stop instance**.
3. Watch `lostfound-tg` → Targets: the stopped instance becomes **unhealthy/draining**.
4. Watch CloudWatch → Alarms → `lostfound-unhealthy-hosts`: record the time **T1** it enters
   **In alarm**.
5. Watch EC2 → Auto Scaling groups → `lostfound-asg` → Activity: record the time **T2** a
   replacement instance is launched, and **T3** when it shows **healthy** in the target group.
6. Record the time **T4** the alarm returns to **OK**. Stop the probe (Ctrl+C).
7. Export the alarm history:
   ```bash
   aws cloudwatch describe-alarm-history --alarm-name lostfound-unhealthy-hosts --history-item-type StateUpdate --max-records 5 --query "AlarmHistoryItems[].[Timestamp,HistorySummary]" --output text
   ```

## Expected result

- Alarm enters ALARM **within 5 minutes** of T0.
- The probe shows `200` throughout, or at most a few failed requests while the ALB marks the
  instance unhealthy (2 failed checks × 15 s ≈ 30 s).
- The ASG launches a replacement automatically; capacity returns to 2 healthy instances with
  no manual action.

## Actual result

| Event | Time | Minutes after T0 |
|---|---|---|
| T0: instance stopped | | 0 |
| Target marked unhealthy | | |
| T1: alarm IN ALARM | | |
| T2: replacement launched | | |
| T3: replacement healthy | | |
| T4: alarm back to OK | | |

| Measure | Value |
|---|---|
| Probe requests during test | |
| Non-200 responses | __ (__%) |
| Longest run of failures | __ seconds |

```
(paste the alarm history export here)
```

**Target (alarm within 5 minutes): MET / NOT MET**

## Interpretation

*(2 to 4 sentences: what users experienced, how long recovery took, what limited it, e.g. the
300 s grace period and package install time in `user-data.sh`.)*

## Improvement plan

*(e.g. bake a custom AMI with packages pre-installed to cut replacement time; enable RDS
Multi-AZ so the database is not a single point of failure; add a 5xx-rate alarm.)*

## Part B: scale-out under load

| Field | Value |
|---|---|
| **Objective** | Show the target-tracking policy (average CPU 50%) adds web servers when load rises and removes them afterwards. |
| **Command** | `python3 tests/load_test.py http://<alb-dns-name> --threads 30 --minutes 12 \| tee evidence/test-load-YYYY-MM-DD.txt` |
| **Why this page** | The script repeatedly opens a matches page. Each request starts the Python matcher, measured locally at 1.5–1.9 s per call, about 1.3 s of which is loading scikit-learn, so it is the most CPU-heavy page in the app. |
| **Expected** | ASG CPU above 50% within a few minutes; `lostfound-asg` desired capacity rises above 2; p95 latency improves once new instances are healthy; capacity returns to 2 about 15 minutes after the load stops. |

Export afterwards:

```bash
aws autoscaling describe-scaling-activities --auto-scaling-group-name lostfound-asg --max-items 6 \
  --query "Activities[].[StartTime,StatusCode,Description]" --output text
```

| Measure | Value |
|---|---|
| Time load started | |
| Peak average CPU (CloudWatch, lostfound-asg) | |
| Time scale-out activity started | |
| Max instances reached | |
| p95 latency before / after scale-out | |
| Errors during the test | |

```
(paste the scaling activities export here)
```

**Interpretation:** *(e.g. whether scale-out happened, how long it took, and the bottleneck it
revealed: per-request matcher start-up; the fix is to compute matches once when a report is
submitted and store them.)*

## Known limitation

The database (`lostfound-db`) is Single-AZ to stay within Learner Lab budget, so it is still a
single point of failure. Recovery for it relies on automated backups (1-day retention,
point-in-time restore), not automatic failover. Multi-AZ RDS is the production fix.
