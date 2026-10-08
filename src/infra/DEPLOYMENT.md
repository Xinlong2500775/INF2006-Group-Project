# AWS Deployment Guide

How Campus Lost & Found is deployed on AWS (AWS Academy Learner Lab, `us-east-1`).
Resource names below are the labels used in `evidence/architecture.png`, the threat-control map
and the evidence files. **If you use different names, update them everywhere.**

Expected cost while running (us-east-1 on-demand list prices, approximate): 2 × t3.micro
≈ US$0.021/h, db.t3.micro ≈ US$0.017/h, ALB ≈ US$0.023/h + usage, so **about US$0.06–0.07 per
hour**, or about US$0.11/h if a NAT gateway is added. Delete everything after collecting
evidence (step 9).

## Architecture summary

```
Internet ──HTTP 80──> lostfound-alb (alb-sg, public subnets, 2 AZs)
                          │ HTTP 80, health check /health.php
                          ▼
           lostfound-asg: 2+ EC2 web servers (web-sg), launch template lostfound-lt
           Apache + PHP + Python matcher, config from SSM Parameter Store
                          │ MySQL 3306
                          ▼
           lostfound-db: RDS MySQL (db-sg, private subnets, not publicly accessible)

CloudWatch: ALB/target metrics + alarm lostfound-unhealthy-hosts
```

## 1. Network: VPC and security groups

Use a VPC with 2 public + 2 private subnets across 2 AZs (same as Lab 5).

| Security group | Inbound rule | Source | Why |
|---|---|---|---|
| `alb-sg` | HTTP 80 | 0.0.0.0/0 | Public entry point, the only thing users can reach |
| `web-sg` | HTTP 80 | **`alb-sg`** (not 0.0.0.0/0) | Web servers only accept traffic from the load balancer |
| `db-sg` | MySQL 3306 | **`web-sg`** only | Database only accepts the web servers |

No SSH rule from 0.0.0.0/0. For shell access use **EC2 Instance Connect** (temporarily add
SSH 22 from your own IP `x.x.x.x/32` only, and remove it after) or Session Manager.

## 2. Database: lostfound-db

1. RDS → Create database → MySQL, Free tier / Dev-Test, `db.t3.micro`, Single-AZ.
2. Identifier `lostfound-db`, master user `admin`, master password of your choice (never committed).
3. VPC: the project VPC; subnet group with the **private** subnets; **Public access: No**;
   security group `db-sg`; initial database name `inf2006`.
4. Automated backups: **on, 1-day retention** (this is the backup/recovery mechanism; free
   within the storage size).

## 3. Load the schema and create the app user

From one temporary EC2 instance in the VPC (or a web server after step 6):

```bash
sudo dnf install -y mariadb105 git
git clone https://github.com/Xinlong2500775/INF2006-Group-Project.git && cd INF2006-Group-Project
mysql -h <rds-endpoint> -u admin -p inf2006 < data/schema.sql
# edit data/db_app_user.sql: replace CHANGE_ME_STRONG_PASSWORD (do NOT commit the edit)
mysql -h <rds-endpoint> -u admin -p inf2006 < data/db_app_user.sql
```

Copy the `SHOW GRANTS` output (it lists only SELECT, INSERT, UPDATE) into `evidence/deployment.md`.

## 4. Secrets: SSM Parameter Store

Systems Manager → Parameter Store → Create parameter:

| Name | Type | Value |
|---|---|---|
| `/lostfound/db_host` | String | the RDS endpoint |
| `/lostfound/db_password` | **SecureString** | the `lostfound_app` password |

The password is encrypted at rest (KMS) and only read by instances at boot through their IAM role.
It never appears in the launch template, Git, or the submission.

## 5. Launch template: lostfound-lt

EC2 → Launch templates → Create:

- AMI: **Amazon Linux 2023**, instance type `t3.micro`, key pair `vockey` (optional)
- Security group: `web-sg`
- Advanced → IAM instance profile: **`LabInstanceProfile`** (gives SSM read access)
- Advanced → User data: paste the whole of `src/infra/user-data.sh`

## 6. Target group, load balancer, Auto Scaling group

1. **Target group `lostfound-tg`:** instances, HTTP 80, VPC = project VPC.
   Health check path **`/health.php`**, healthy threshold 2, unhealthy threshold 2, interval 15 s.
   After creating it: Attributes → Edit → **Stickiness: on, load balancer cookie, 1 day**.
   (PHP login sessions are stored on each server's disk; without stickiness a user's next
   request can land on the other server and they appear logged out.)
2. **ALB `lostfound-alb`:** internet-facing, both **public** subnets, security group `alb-sg`,
   listener HTTP 80 → `lostfound-tg`.
3. **Auto Scaling group `lostfound-asg`:** launch template `lostfound-lt`, subnets in **both AZs**
   (public subnets with auto-assign public IP, or private subnets if a NAT gateway exists: the
   instances need outbound internet to install packages and clone the repo).
   - Attach to `lostfound-tg`, turn on **ELB health checks**, grace period 300 s
   - Desired 2, minimum 2, maximum 4
   - Target tracking policy: average CPU 50%

Wait until both targets show **healthy**, then open `http://<alb-dns-name>/`.

## 7. Monitoring: CloudWatch alarm

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

## 8. Collect evidence (text exports preferred over screenshots)

Run in **CloudShell**, redact account IDs and IPs, and save the output into `evidence/deployment.md`:

```bash
aws ec2 describe-security-groups --filters Name=group-name,Values=alb-sg,web-sg,db-sg \
  --query "SecurityGroups[].{Name:GroupName,In:IpPermissions[].{Port:FromPort,From:IpRanges[].CidrIp,FromSG:UserIdGroupPairs[].GroupId}}"
aws rds describe-db-instances --db-instance-identifier lostfound-db \
  --query "DBInstances[].{Engine:Engine,Class:DBInstanceClass,Public:PubliclyAccessible,MultiAZ:MultiAZ,Backup:BackupRetentionPeriod,SG:VpcSecurityGroups[].VpcSecurityGroupId}"
aws autoscaling describe-auto-scaling-groups --auto-scaling-group-names lostfound-asg \
  --query "AutoScalingGroups[].{Min:MinSize,Max:MaxSize,Desired:DesiredCapacity,AZs:AvailabilityZones,HealthCheck:HealthCheckType}"
aws elbv2 describe-target-health --target-group-arn <lostfound-tg-arn>
```

Then run the tests against the ALB (`tests/README.md`) and the resilience test
(`evidence/test-resilience.md`).

## 9. Clean up (cost control)

Delete in this order: Auto Scaling group → ALB → target group → launch template → RDS
(skip final snapshot) → NAT gateway + Elastic IP (if any) → VPC → SSM parameters → log group
`/lostfound/httpd`. Learner Lab
also stops instances when the session ends, but ALB, RDS and NAT gateways keep using credit.
