# Deployment Record

Dated, redacted record of the AWS deployment. Account IDs, public IPs, endpoints and ARNs
are replaced with `<redacted>`. How it was built: `src/infra/DEPLOYMENT.md`.

| Field | Value |
|---|---|
| Date deployed | `YYYY-MM-DD` *(fill in)* |
| Deployed by | *(name)* |
| Region | us-east-1 (AWS Academy Learner Lab) |
| Repo commit deployed | `<commit hash>` |
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
(paste output of: aws ec2 describe-security-groups ... from DEPLOYMENT.md step 8)
```

Check: `web-sg` inbound source is `alb-sg` only; `db-sg` inbound source is `web-sg` only; no
`0.0.0.0/0` on 22 or 3306.

## RDS configuration (exported)

```
(paste output of: aws rds describe-db-instances ...)
```

Check: `"Public": false`.

## Database account privileges

```
(paste output of: SHOW GRANTS FOR 'lostfound_app'@'%';)
```

Check: only SELECT, INSERT, UPDATE on `users`, `items`, `claims`.

## Auto Scaling group and target health (exported)

```
(paste output of: aws autoscaling describe-auto-scaling-groups ... and aws elbv2 describe-target-health ...)
```

Check: 2 instances in 2 different AZs, both `healthy`.

## Database not reachable from the internet

```
(paste output of, from a laptop: Test-NetConnection <rds-endpoint> -Port 3306   or   nc -vz -w 5 <rds-endpoint> 3306)
```

Expected: connection fails / times out (`TcpTestSucceeded : False`).
