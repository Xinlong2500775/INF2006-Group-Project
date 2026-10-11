# Test 4: Scalability and Resilience Test

| Field | Value |
|---|---|
| **Objective** | Show that the service keeps working when a web server fails, and that the failure is detected and repaired automatically (report §1.2: CloudWatch alarm goes off within 5 minutes of a fault caused on purpose, e.g. stopping an EC2 instance). |
| **Mechanisms tested** | ALB health checks on `/health.php` across 2 AZs; Auto Scaling group `lostfound-asg` (min 1, desired 2, max 3) replacing unhealthy instances; CloudWatch alarm `lostfound-unhealthy-alarm`. |
| **Date** | Part A: 2026-10-11, 17:30 SGT (UTC+8) · Part B: 2026-10-11, 18:43 SGT |
| **Setup** | Deployed as in `src/infra/DEPLOYMENT.md`. Both targets in `lostfound-tg` healthy. Alarm in OK state. |
| **Artefacts** | `evidence/test-resilience-log-2026-10-11.txt` (availability probe output, one `/health.php` request about every 5 s), CloudWatch alarm history (below and in `evidence/monitoring.md`), `evidence/deployment.md` |

## Steps

1. **Start an availability probe** (laptop or CloudShell). It requests the site every 5 seconds
   and records the HTTP status, so we can see whether users were affected:

   ```bash
   # Bash / CloudShell
   while true; do echo "$(date +%T) $(curl -s -o /dev/null -w '%{http_code}' --max-time 5 http://<alb-dns-name>/health.php)"; sleep 5; done
   ```
   ```powershell
   # Windows PowerShell
   while ($true) { try { $c = (Invoke-WebRequest "http://<alb-dns-name>/health.php" -UseBasicParsing -TimeoutSec 5).StatusCode } catch { $c = "ERR" }; "$(Get-Date -Format HH:mm:ss) $c"; Start-Sleep 5 }
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
Alarm times are taken from the CloudWatch alarm history (authoritative); the other times were read
from the console during the test.

| Event | Where checked | Time (SGT) | After stop (≈17:30:20) |
|---|---|---|---|
| T0: one `lostfound-web` instance stopped (last probe 200 at 17:30:19, first failure at 17:30:25) | EC2 → Instances, probe log | ≈17:30:20 | 0:00 |
| ASG registers the stopped instance and starts terminating it | ASG → Activity | 17:30:49 | 0:29 |
| Replacement launched by `lostfound-asg` | ASG → Activity | 17:30:51 | 0:31 |
| Stopped target marked unhealthy / draining | `lostfound-tg` → Targets | 17:31:10 | 0:50 |
| T3: replacement healthy in `lostfound-tg` | `lostfound-tg` → Targets | 17:34:05 | 3:45 |
| T1: alarm `lostfound-unhealthy-alarm` OK → In alarm | CloudWatch alarm history | 17:34:37 | **4:17** |
| T4: alarm In alarm → OK | CloudWatch alarm history | 17:35:37 | 5:17 |

| Measure | Value |
|---|---|
| Probe window | 17:27:33 – 17:40:28 |
| Probe requests (about every 5 s) | 144 |
| HTTP 200 | 142 |
| Failed requests | 2 (1.4%), at 17:30:25 and 17:30:35 |
| Failures after 17:30:35 | None |
| Site stayed up | Yes, on the remaining instance |

Auto Scaling activity (console export, 2026-10-11):

| Status | Description | Start (SGT) | End (SGT) | Cause |
|---|---|---|---|---|
| Connection draining in progress | Terminating EC2 instance `i-024dfbf7458b69d26` (waiting for ELB connection draining) | 17:30:49 | in progress | Instance taken out of service after an EC2 health check showed it had been stopped |
| Successful | Launching a new EC2 instance `i-0ebed879272f4e01e` | 17:30:51 | 17:30:56 | Launched to replace an unhealthy instance |

Alarm history, `lostfound-unhealthy-alarm` (condition `UnHealthyHostCount` ≥ 1 for 1 datapoint
within 1 minute; console History tab, 2026-10-11, times in SGT):

| Time | State change | Linked to |
|---|---|---|
| 17:34:37 | OK → In alarm | Part A: instance stopped at ≈17:30:20 |
| 17:35:37 | In alarm → OK | Part A: replacement healthy, target group back to 2 healthy |
| 18:22:37 | OK → In alarm | 60-thread load run (18:19–18:29), see Part B |
| 18:23:37 | In alarm → OK | |
| 18:24:37 | OK → In alarm | 60-thread load run |
| 18:25:37 | In alarm → OK | |
| 18:53:37 | OK → In alarm | Just after the 30-thread scaling run (18:43–18:51:49) |
| 18:54:37 | In alarm → OK | |

**Target (alarm within 5 minutes): MET (about 4 minutes: 4 min 17 s after the stop, 3 min 48 s after the ASG registered it).**

## Interpretation

Users were barely affected: 2 of 144 probe requests failed, both within 15 seconds of the stop
(17:30:25 and 17:30:35), while the load balancer was still sending some requests to the stopped
instance; every request after that succeeded. The
Auto Scaling group noticed the stopped instance through its EC2 health check and launched a
replacement 2 seconds after registering it, with no manual action; the replacement passed
`/health.php` about 3 min 45 s after the stop, most of which is `user-data.sh` installing packages and
cloning the repository. The alarm fired about 4 minutes after the stop, inside the 5-minute target
but with less margin than we expected: the target is only marked unhealthy after 2 failed checks,
the ALB publishes `UnHealthyHostCount` once a minute, and CloudWatch then evaluates it. For those
minutes the site ran on one instance, so a second failure in that window would have caused an outage.
The console readings we took during the test were about a minute earlier than CloudWatch's own
history, so we report the alarm history times.

## Improvement plan

- Bake a custom AMI with Apache, PHP, Python and scikit-learn pre-installed, so a replacement is
  healthy in under a minute instead of about 3.
- Add an SNS email target to `lostfound-unhealthy-alarm` so the team is notified, not just shown
  the alarm in the console, and add a second alarm on the ALB 5xx count.
- Shorten the health-check interval (e.g. 10 s) so a dead instance is detected and the alarm fires sooner.
- Enable RDS Multi-AZ so the database is not the remaining single point of failure.

## Part B: scale-out under load

| Field | Value |
|---|---|
| **Objective** | Show that the target-tracking policy adds a web server when load rises. |
| **Command** | `py tests\load_test.py http://lostfound-alb-1449062045.us-east-1.elb.amazonaws.com --threads 30 --minutes 10` (stopped manually at 18:51:49) |
| **Why this page** | The script repeatedly opens a matches page. Each request starts the Python matcher, measured locally at 1.5–1.9 s per call (about 1.3 s of it loading scikit-learn), so it is the most CPU-heavy page in the app. |
| **Setup** | `lostfound-asg` at 2 healthy instances (`i-0ebed879272f4e01e`, `i-048a8ce725129c48c`), max 3. Target-tracking policy on average CPU, 300 s instance warm-up, scale-in enabled. |
| **Change for this test** | A first run with 60 threads (18:19–18:29) at the normal **50%** target did **not** scale out. The access logs (`evidence/monitoring.md`) show its logins failed, so about 50,000 of its requests were cheap redirects to the login page rather than matching. For the second run (30 threads, logins working) the target was lowered to **20%** to confirm the scale-out mechanism itself, then restored to 50% afterwards. The group's **minimum was also raised from 1 to 2** for the duration of testing, so it could not scale in below two servers mid-test, and was set back to 1 afterwards (min 1 / desired 2 / max 3, as in `evidence/deployment.md`). |
| **Expected** | Average CPU above the target within a few minutes; desired capacity rises from 2 to 3; the new instance passes the ALB health check. |

| Step | Event | Where checked | Time (SGT) | After B0 |
|---|---|---|---|---|
| B0 | Load test started (30 threads) | Terminal | 18:43:00 | 0:00 |
| B1 | Average CPU above the 20% target | ASG → Monitoring | 18:45:00 | 2:00 |
| B2 | `TargetTracking-lostfound-asg-AlarmHigh` → In alarm, scaling policy executed in the same second | CloudWatch alarm history | 18:49:33 | 6:33 |
| B3 | ASG launched a 3rd instance (desired 2 → 3), `i-072706211337f32c3` | ASG → Activity | 18:49:45 | 6:45 |
| B4 | 3rd instance healthy | `lostfound-tg` → Targets | 18:51:00 | 8:00 |
| B5 | Load test stopped | Terminal | 18:51:49 | 8:49 |

| Measure | Value |
|---|---|
| Peak average CPU (`lostfound-asg`) | 30.8% |
| Max instances reached | **3** (scale-out: yes) |
| Requests sent | 518 |
| Successful responses | 425 (82.0%) |
| Errors | 93 (18.0%), client-side 30 s timeouts while the servers were saturated |
| Response time | p50 about 28.7 s, p95 about 29–30 s |

Auto Scaling activity (console, 2026-10-11):

| Status | Description | Start (SGT) |
|---|---|---|
| Successful | Launching a new EC2 instance: `i-072706211337f32c3` | 18:49:45 |

Alarm history, `TargetTracking-lostfound-asg-AlarmHigh` (average CPU > 20%; times in SGT):

| Time | Event |
|---|---|
| 18:39:56 | Alarm created when the target-tracking value was changed to 20% |
| 18:40:33 | Insufficient data → OK |
| 18:49:33 | OK → In alarm; scaling policy `lostfound-asg / Target Tracking Scaling Policy` executed |
| 18:54:33 | In alarm → OK (load stopped at 18:51:49) |

**Result: scale-out MET at the lowered 20% target; NOT triggered at the normal 50% target.**

**Interpretation:** Under 30 concurrent users on the matches page, average CPU passed the 20% target
within 2 minutes, and the policy scaled the group from 2 to 3 instances 6 min 45 s after the test
started; the new instance was serving traffic 1 min 15 s later. So the mechanism works, but the test
also showed two weaknesses. First, CPU is a poor signal for this app: requests were already taking
about 29 s, with 18% timing out on the client (and 85 × 504 logged by the servers), while average CPU
peaked at 30.8%, so at the normal 50% target the group would not have grown at all. The likely reasons are that requests queue for PHP workers while
each one waits on a Python process, and that EC2 basic monitoring averages CPU over 5 minutes, which
also explains why the policy took about 4.5 minutes to react after B1. Second, the per-request matcher
is the bottleneck, as expected. The unhealthy-host alarm also fired briefly (1 minute each) at 18:22,
18:24 and 18:53, during and just after the load runs: busy servers answered the ALB health check too
slowly and were briefly marked unhealthy. Under heavy load the ALB could therefore pull working
servers out of service, which makes the overload worse.

**Improvement plan:** scale on ALB `RequestCountPerTarget` or `TargetResponseTime` instead of CPU;
enable 1-minute detailed monitoring so the policy reacts faster; and compute matches once when a
report is submitted (stored in the database) so the matches page no longer starts Python on every
request.

## Known limitation

The database (`lostfound-db`) is Single-AZ to stay within Learner Lab budget, so it is still a
single point of failure. Recovery for it relies on automated backups (1-day retention,
point-in-time restore), not automatic failover. Multi-AZ RDS is the production fix.
