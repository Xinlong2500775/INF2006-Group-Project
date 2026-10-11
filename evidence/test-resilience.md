# Test 4: Scalability and Resilience Test

| Field | Value |
|---|---|
| **Objective** | Show that the service keeps working when a web server fails, and that the failure is detected and repaired automatically (report §1.2: CloudWatch alarm goes off within 5 minutes of a fault caused on purpose, e.g. stopping an EC2 instance). |
| **Mechanisms tested** | ALB health checks on `/health.php` across 2 AZs; Auto Scaling group `lostfound-asg` (min 1, desired 2, max 3) replacing unhealthy instances; CloudWatch alarm `lostfound-unhealthy-alarm`. |
| **Date** | Part A: 2026-10-11, 17:30 SGT (UTC+8) · Part B: *(TODO: date)* |
| **Setup** | Deployed as in `src/infra/DEPLOYMENT.md`. Both targets in `lostfound-tg` healthy. Alarm in OK state. |
| **Artefacts** | `evidence/test-resilience-log-2026-10-11.txt` (availability probe output), CloudWatch alarm history (exported text or one redacted screenshot), `evidence/deployment.md` |

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
4. Watch CloudWatch → Alarms → `lostfound-unhealthy-alarm`: record the time **T1** it enters
   **In alarm**.
5. Watch EC2 → Auto Scaling groups → `lostfound-asg` → Activity: record the time **T2** a
   replacement instance is launched, and **T3** when it shows **healthy** in the target group.
6. Record the time **T4** the alarm returns to **OK**. Stop the probe (Ctrl+C).
7. Export the alarm history:
   ```bash
   aws cloudwatch describe-alarm-history --alarm-name lostfound-unhealthy-alarm --history-item-type StateUpdate --max-records 5 --query "AlarmHistoryItems[].[Timestamp,HistorySummary]" --output text
   ```

## Expected result

- Alarm enters ALARM **within 5 minutes** of T0.
- The probe shows `200` throughout, or at most a few failed requests while the ALB marks the
  instance unhealthy (2 failed checks × 15 s ≈ 30 s).
- The ASG launches a replacement automatically; capacity returns to the desired 2 healthy
  instances with no manual action.

## Actual result (Part A, 2026-10-11)

Instance stopped: `i-024dfbf7458b69d26` · replacement launched by the ASG: `i-0ebed879272f4e01e`.

| Event | Where checked | Time (SGT) | After T0 |
|---|---|---|---|
| T0: one `lostfound-web` instance stopped | EC2 → Instances | 17:30:49 | 0:00 |
| Replacement launched by `lostfound-asg` | ASG → Activity | 17:30:51 | 0:02 |
| Stopped target marked unhealthy / draining | `lostfound-tg` → Targets | 17:31:10 | 0:21 |
| T1: alarm `lostfound-unhealthy-alarm` → In alarm | CloudWatch → Alarms | 17:33:12 | **2:23** |
| T3: replacement healthy in `lostfound-tg` | `lostfound-tg` → Targets | 17:34:05 | 3:16 |
| T4: alarm back to OK | CloudWatch → Alarms | 17:34:06 | 3:17 |

| Measure | Value |
|---|---|
| Probe requests during test (every 5 s) | 92 |
| HTTP 200 | 90 |
| Non-200 responses | 2 (2.2%) |
| Site stayed up | Yes, on the remaining instance |

Auto Scaling activity (console export, 2026-10-11):

| Status | Description | Start (SGT) | End (SGT) | Cause |
|---|---|---|---|---|
| Connection draining in progress | Terminating EC2 instance `i-024dfbf7458b69d26` (waiting for ELB connection draining) | 17:30:49 | in progress | Instance taken out of service after an EC2 health check showed it had been stopped |
| Successful | Launching a new EC2 instance `i-0ebed879272f4e01e` | 17:30:51 | 17:30:56 | Launched to replace an unhealthy instance |

```
(TODO: paste the output of: aws cloudwatch describe-alarm-history --alarm-name lostfound-unhealthy-alarm --history-item-type StateUpdate --max-records 5 --query "AlarmHistoryItems[].[Timestamp,HistorySummary]" --output text)
```

**Target (alarm within 5 minutes): MET (2 min 23 s).**

## Interpretation

Users were barely affected: 2 of 92 probe requests failed, most likely in the short window before the
load balancer stopped routing to the stopped instance (marked unhealthy after 21 s, consistent with
2 failed checks at a 15 s interval). The Auto Scaling group noticed the stopped instance through its
EC2 health check and launched a replacement 2 seconds later, with no manual action; the replacement
passed `/health.php` 3 min 16 s after the fault, most of which is `user-data.sh` installing packages
and cloning the repository. The alarm fired after 2 min 23 s, inside the 5-minute target; the delay
comes from the 1-minute metric period and the time the ALB takes to publish the metric. For about
3 minutes the site ran on one instance, so a second failure in that window would have caused an outage.

## Improvement plan

- Bake a custom AMI with Apache, PHP, Python and scikit-learn pre-installed, so a replacement is
  healthy in under a minute instead of about 3.
- Add an SNS email target to `lostfound-unhealthy-alarm` so the team is notified, not just shown
  the alarm in the console, and add a second alarm on the ALB 5xx count.
- Enable RDS Multi-AZ so the database is not the remaining single point of failure.

## Part B: scale-out under load

| Field | Value |
|---|---|
| **Objective** | Show the target-tracking policy (average CPU 50%) adds web servers when load rises and removes them afterwards. |
| **Command** | `python3 tests/load_test.py http://<alb-dns-name> --threads 30 --minutes 12 \| tee evidence/test-load-YYYY-MM-DD.txt` |
| **Why this page** | The script repeatedly opens a matches page. Each request starts the Python matcher, measured locally at 1.5–1.9 s per call, about 1.3 s of which is loading scikit-learn, so it is the most CPU-heavy page in the app. |
| **Expected** | ASG CPU above 50% within a few minutes; `lostfound-asg` desired capacity rises to 3 (the maximum); p95 latency improves once new instances are healthy; capacity returns to 2 about 15 minutes after the load stops (max 3 caps the scale-out). |

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
