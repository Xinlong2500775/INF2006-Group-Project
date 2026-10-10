# Deployment Record

Dated, redacted record of the AWS deployment. Account IDs, public IPs, endpoints and ARNs
are replaced with `<redacted>`. How it was built: `src/infra/DEPLOYMENT.md`.

| Field | Value |
|---|---|
| Date deployed | `1 October 2026` |
| Deployed by | `Zhou Xinlong` |
| Region | us-east-1 (AWS Academy Learner Lab) |
| Repo commit deployed | `1bdf450` |
| Launch script | `src/infra/user-data.sh` |

## Resources

| Component (label in architecture diagram) | AWS resource | Key settings |
|---|---|---|
| Load balancer | ALB `lostfound-alb` | Internet-facing, 2 AZs, HTTP 80 → `lostfound-tg` |
| Target group | `lostfound-tg` | Health check `/health.php`, healthy 2 / unhealthy 2, 15 s |
| Web tier | ASG `lostfound-asg` + launch template `lostfound-lt`, instances named `lostfound-web` | t3.micro, Amazon Linux 2023, private app subnets in 2 AZs, min 1 / desired 2 / max 3, CPU 50% target tracking, ELB health checks |
| Database | RDS `lostfound-db` | MySQL, db.t3.micro, private subnets, Publicly accessible = No, backups 1 day |
| Credentials | Local config file `src/.env` on each instance | Written at boot by the launch template user data, mode 640; no Secrets Manager |
| Network egress | NAT gateway (1 AZ) | Lets private web servers install packages and pull the repo |
| Cost | AWS Budgets alert | Monthly limit, alert at 80% |
| Monitoring | CloudWatch alarm `lostfound-unhealthy-hosts` | UnHealthyHostCount ≥ 1, 1 × 1 min |

## Security group rules (exported, redacted)

```
$ aws ec2 describe-security-groups --filters Name=group-name,Values=alb-sg,web-sg,db-sg (run 2026-10-10)
Port  CIDR        Source security group
80    0.0.0.0/0   None                    <- alb-sg: HTTP from the internet
80    None        sg-0531f60f31673c54e    <- web-sg: HTTP only from alb-sg
3306  None        sg-02624bb73d1d5bf5b    <- db-sg: MySQL only from web-sg
```

Result: only `alb-sg` is open to the internet (port 80). `web-sg` accepts HTTP only from the
load balancer's security group, and `db-sg` accepts MySQL 3306 only from the web servers'
security group. No `0.0.0.0/0` on port 22 or 3306. **PASS**

## RDS configuration (exported)

```
$ aws rds describe-db-instances --db-instance-identifier lostfound-db (run 2026-10-10)
Identifier    Engine  Class        Public  MultiAZ  BackupDays  Encrypted
lostfound-db  mysql   db.t3.micro  False   False    1           True
```

Result: the database is not publicly accessible, runs Single-AZ (cost decision, see report §3.3),
keeps 1 day of automated backups, and its storage is encrypted at rest. **PASS**

## Database account privileges

```
$ mysql -h <redacted> -u admin -p inf2006 < data/db_app_user.sql   (run on lostfound-setup, 2026-10-10)
+--------------------------------------------------------------------------------+
| Grants for lostfound_app@%                                                     |
+--------------------------------------------------------------------------------+
| GRANT USAGE ON *.* TO `lostfound_app`@`%`                                      |
| GRANT SELECT, INSERT, UPDATE ON `inf2006`.`claims` TO `lostfound_app`@`%`      |
| GRANT SELECT, INSERT, UPDATE ON `inf2006`.`items` TO `lostfound_app`@`%`       |
| GRANT SELECT, INSERT, UPDATE ON `inf2006`.`users` TO `lostfound_app`@`%`       |
+--------------------------------------------------------------------------------+
```

Result: the app account can only read, add and update rows in the three app tables. It has no
DELETE, DROP, ALTER or GRANT rights, and the RDS master account is only used to load the schema.
`GRANT USAGE` only allows login. **PASS**

## Auto Scaling group and target health (exported)

```
$ aws autoscaling describe-auto-scaling-groups --auto-scaling-group-names lostfound-asg (run 2026-10-10)
Name           Min  Desired  Max  HealthCheck  GracePeriod
lostfound-asg  1    2        3    ELB          300

Instance             AZ          Health   State
i-0876c6fd149ced762  us-east-1a  Healthy  InService
i-06e0222728524b172  us-east-1b  Healthy  InService

$ aws elbv2 describe-target-health (lostfound-tg)
i-0876c6fd149ced762  healthy
i-06e0222728524b172  healthy
```

Result: the Auto Scaling group runs 2 instances (min 1, max 3) in two different Availability
Zones with ELB health checks, and both pass the /health.php check behind the ALB. **PASS**

## Database not reachable from the internet

```
PS> Test-NetConnection <redacted-rds-endpoint> -Port 3306   (from a team laptop on home Wi-Fi, 2026-10-10)
WARNING: TCP connect to (<redacted> : 3306) failed
WARNING: Ping to <redacted> failed with status: TimedOut

RemotePort       : 3306
PingSucceeded    : False
TcpTestSucceeded : False
```

Result: the RDS endpoint resolves only to a private VPC address and cannot be reached from the
internet on port 3306. **PASS**
