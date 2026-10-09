# AWS Deployment Guide

How Campus Lost & Found is deployed on AWS (AWS Academy Learner Lab, `us-east-1`).
This guide builds exactly what `evidence/architecture.png` shows. Resource names below are the
labels used in the diagram, the threat-control map and the evidence files.
**If you build anything differently (names, sizes, subnets), tell the team so the diagram,
report and evidence are updated to match.**

Expected cost while running (us-east-1 on-demand list prices, approximate): 2 × t3.micro
≈ US$0.021/h, db.t3.micro ≈ US$0.017/h, ALB ≈ US$0.023/h, NAT gateway ≈ US$0.045/h, so
**about US$0.11 per hour** plus small data charges. Delete everything after collecting evidence
(step 10).

## Architecture summary (matches evidence/architecture.png)

```
Users ──HTTP 80──> Internet Gateway ──> lostfound-alb (alb-sg, public subnets, AZ A + AZ B)
                                            │ HTTP 80, health check /health.php, sticky sessions
            ── trust boundary: web-sg accepts HTTP only from alb-sg ──
                                            ▼
        lostfound-asg (min 1 · desired 2 · max 3), private app subnets, AZ A + AZ B
        EC2 "lostfound-web" t3.micro: Apache + PHP + Python TF-IDF matcher
        DB login from a local config file (src/.env), no Secrets Manager
                                            │ MySQL 3306
            ── trust boundary: db-sg accepts MySQL only from web-sg ──
                                            ▼
        lostfound-db: RDS MySQL, Single-AZ, db.t3.micro, private DB subnets, not public

CloudWatch: logs, metrics, alarm lostfound-unhealthy-hosts · AWS Budgets: cost alert
```

## 1. Network: VPC, subnets and security groups

VPC → Create VPC → **VPC and more** (same wizard as Lab 5):

- 2 Availability Zones, **2 public subnets** (for the ALB) and **2 private subnets** (for the
  web servers). Add 2 more private subnets for the database (or reuse the private subnets for
  the RDS subnet group, as in Lab 5).
- **NAT gateway: Zonal, in 1 AZ.** The web servers are in private subnets, so they need the NAT
  gateway to download packages and clone the repo at boot. One NAT gateway keeps cost down.

Security groups:

| Security group | Inbound rule | Source | Why |
|---|---|---|---|
| `alb-sg` | HTTP 80 | 0.0.0.0/0 | Public entry point, the only thing users can reach |
| `web-sg` | HTTP 80 | **`alb-sg`** (not 0.0.0.0/0) | Web servers only accept traffic from the load balancer |
| `db-sg` | MySQL 3306 | **`web-sg`** only | Database only accepts the web servers |

No SSH from the internet. The web servers have no public IP; for shell access use
**EC2 Instance Connect Endpoint** or Session Manager.

## 2. Database: lostfound-db

1. RDS → Create database → MySQL, Free tier / Dev-Test, `db.t3.micro`, **Single-AZ**.
2. Identifier `lostfound-db`, master user `admin`, master password of your choice (never committed).
3. VPC: the project VPC; subnet group with the **private** subnets; **Public access: No**;
   security group `db-sg`; initial database name `inf2006`.
4. Automated backups: **on, 1-day retention** (the backup/recovery mechanism; free within the
   storage size).

## 3. Load the schema and create the app user

From one web server after step 6 (EC2 Instance Connect Endpoint), or a temporary instance in the VPC:

```bash
sudo dnf install -y mariadb105 git
git clone https://github.com/Xinlong2500775/INF2006-Group-Project.git && cd INF2006-Group-Project
mysql -h <rds-endpoint> -u admin -p inf2006 < data/schema.sql
# edit data/db_app_user.sql: replace CHANGE_ME_STRONG_PASSWORD (do NOT commit the edit)
mysql -h <rds-endpoint> -u admin -p inf2006 < data/db_app_user.sql
```

Copy the `SHOW GRANTS` output (it lists only SELECT, INSERT, UPDATE) into `evidence/deployment.md`.

## 4. Credentials: local config file (no Secrets Manager)

The app reads its database login from `src/.env` on each server. `src/infra/user-data.sh`
writes that file at boot with permissions 640 (root and Apache only), outside the public web
folder. The two real values go **only into the launch template's copy** of the script (step 5):

```bash
DB_HOST='<rds-endpoint>'     # replace with the RDS endpoint
DB_PASSWORD='<db-password>'  # replace with the lostfound_app password from step 3
```

Never put real values in the Git copy. `tests/check_secrets.py` fails if you do.
(Known limitation: anyone with permission to view the launch template can read its user data.
Production fix: AWS Secrets Manager, as noted in report §7.3.)

## 5. Launch template: lostfound-lt

EC2 → Launch templates → Create:

- Name `lostfound-lt`; AMI **Amazon Linux 2023**; instance type `t3.micro`
- Network: security group `web-sg`, **no public IP** (private subnets)
- Resource tags: **`Name` = `lostfound-web`** (the name shown in the diagram)
- Advanced → IAM instance profile: **`LabInstanceProfile`** (the "EC2 IAM role" in the diagram;
  lets the CloudWatch agent send logs)
- Advanced → User data: paste the whole of `src/infra/user-data.sh`, then fill in `DB_HOST`
  and `DB_PASSWORD` (step 4)

## 6. Target group, load balancer, Auto Scaling group

1. **Target group `lostfound-tg`:** instances, HTTP 80, VPC = project VPC.
   Health check path **`/health.php`**, healthy threshold 2, unhealthy threshold 2, interval 15 s.
   After creating it: Attributes → Edit → **Stickiness: on, load balancer cookie, 1 day**.
   (PHP login sessions are stored on each server's disk; without stickiness a user's next
   request can land on the other server and they appear logged out.)
2. **ALB `lostfound-alb`:** internet-facing, both **public** subnets, security group `alb-sg`,
   listener HTTP 80 → `lostfound-tg`.
3. **Auto Scaling group `lostfound-asg`:** launch template `lostfound-lt`, the **2 private app
   subnets** (one per AZ).
   - Attach to `lostfound-tg`, turn on **ELB health checks**, grace period 300 s
   - **Minimum 1, desired 2, maximum 3**
   - Target tracking policy: average CPU 50%

Wait until both targets show **healthy** (5–8 minutes, the script installs packages first), then
open `http://<alb-dns-name>/`.

## 7. Monitoring: CloudWatch alarm and logs

CloudWatch → Alarms → Create alarm:

- Metric: ApplicationELB → Per AppELB, per TG Metrics → `lostfound-tg` / `lostfound-alb` →
  **UnHealthyHostCount**
- Statistic Maximum, period 1 minute; condition **≥ 1** for 1 of 1 datapoints
- Name **`lostfound-unhealthy-hosts`**; notification optional (an SNS email topic if allowed)

**Logs:** `user-data.sh` installs the CloudWatch agent, which ships Apache and PHP logs to the log
group **`/lostfound/httpd`** with 7-day retention. Check CloudWatch → Log groups a few minutes
after the instances start. If the group never appears, the Learner Lab role blocked the agent:
the app still works, and the logs can be read on each instance instead (see
`evidence/monitoring.md`).

## 8. Cost control: AWS Budgets alert

Billing → Budgets → Create budget → Cost budget, monthly, e.g. **US$20**, alert at 80% to the
team's email. **If the Learner Lab does not allow Budgets, tell the team**: the "AWS Budgets"
box must then be removed from the diagram and the report, because it can't be evidenced.

## 9. Collect evidence (text exports preferred over screenshots)

Run in **CloudShell**, redact account IDs and IPs, and save the output into `evidence/deployment.md`:

```bash
aws ec2 describe-security-groups --filters Name=group-name,Values=alb-sg,web-sg,db-sg \
  --query "SecurityGroups[].{Name:GroupName,In:IpPermissions[].{Port:FromPort,From:IpRanges[].CidrIp,FromSG:UserIdGroupPairs[].GroupId}}"
aws rds describe-db-instances --db-instance-identifier lostfound-db \
  --query "DBInstances[].{Engine:Engine,Class:DBInstanceClass,Public:PubliclyAccessible,MultiAZ:MultiAZ,Backup:BackupRetentionPeriod,SG:VpcSecurityGroups[].VpcSecurityGroupId}"
aws autoscaling describe-auto-scaling-groups --auto-scaling-group-names lostfound-asg \
  --query "AutoScalingGroups[].{Min:MinSize,Max:MaxSize,Desired:DesiredCapacity,AZs:AvailabilityZones,HealthCheck:HealthCheckType}"
aws elbv2 describe-target-health --target-group-arn <lostfound-tg-arn>
aws budgets describe-budgets --account-id <account-id> --query "Budgets[].{Name:BudgetName,Limit:BudgetLimit}"
```

Then run the tests against the ALB (`tests/README.md`) and the resilience test
(`evidence/test-resilience.md`).

## 10. Clean up (cost control)

Delete in this order: Auto Scaling group → ALB → target group → launch template → RDS
(skip final snapshot) → NAT gateway → release its Elastic IP → VPC → log group
`/lostfound/httpd` → budget. The Learner Lab stops instances when the session ends, but the ALB,
RDS and NAT gateway keep using credit until deleted.
