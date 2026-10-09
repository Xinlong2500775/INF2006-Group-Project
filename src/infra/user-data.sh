#!/bin/bash
# =============================================================================
# EC2 launch script (user data) for Campus Lost & Found web servers.
# Used by the launch template "lostfound-lt" (instances tagged Name=lostfound-web), so every instance the Auto
# Scaling group starts configures itself the same way, with no manual steps.
#
# Target OS: Amazon Linux 2023. Region: us-east-1.
#
# Credentials: the app reads the database login from a local config file
# (src/.env, mode 640, outside the web root). This script writes that file.
# The real endpoint and password are typed into the launch template's copy of
# this script in the AWS console ONLY. The copy in Git keeps the <placeholders>
# below and must never contain real values (tests/check_secrets.py checks this).
# =============================================================================
set -euo pipefail

# ---- Fill in ONLY in the launch template, never in Git ----------------------
DB_HOST='<rds-endpoint>'        # RDS console > lostfound-db > Connectivity > Endpoint
DB_PASSWORD='<db-password>'     # password of the lostfound_app database user
# ---- Usually no need to change -----------------------------------------------
DB_USER="lostfound_app"
DB_NAME="inf2006"
REPO_URL="https://github.com/Xinlong2500775/INF2006-Group-Project.git"
REPO_REF="main"                         # or a commit hash for a pinned release
# -----------------------------------------------------------------------------

case "$DB_HOST$DB_PASSWORD" in
  *"<"*) echo "user-data: fill in DB_HOST and DB_PASSWORD in the launch template first" >&2; exit 1 ;;
esac
set -x   # log the remaining steps (after the password lines, so it is not printed)

APP_DIR=/var/www/lostfound

# 1. Packages: Apache, PHP (with MySQL driver), Python for the matching feature
dnf install -y httpd php php-fpm php-mysqlnd git python3-pip mariadb105

# 2. Application code (outside the public web folder except src/public)
rm -rf "$APP_DIR"
git clone "$REPO_URL" "$APP_DIR"
git -C "$APP_DIR" checkout "$REPO_REF"
python3 -m pip install -r "$APP_DIR/analytics/requirements.txt"

# 3. Database settings: written to src/.env, readable only by root and the
#    apache group, outside the public web folder
umask 027
set +x
cat > "$APP_DIR/src/.env" <<EOF
DB_HOST=$DB_HOST
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD
DB_NAME=$DB_NAME
EOF
set -x
chown root:apache "$APP_DIR/src/.env"
chmod 640 "$APP_DIR/src/.env"
unset DB_PASSWORD

# 4. Apache: only src/public is web-accessible
cat > /etc/httpd/conf.d/lostfound.conf <<EOF
DocumentRoot "$APP_DIR/src/public"
<Directory "$APP_DIR/src/public">
    Options -Indexes
    AllowOverride None
    Require all granted
</Directory>
DirectoryIndex login.php
ServerTokens Prod
ServerSignature Off
EOF
sed -i 's/^expose_php = On/expose_php = Off/' /etc/php.ini || true

# 5. Start services
systemctl enable --now php-fpm httpd

# 6. Self-check (shows in /var/log/cloud-init-output.log)
sleep 3
curl -s -o /dev/null -w "health.php -> HTTP %{http_code}\n" http://localhost/health.php

# 7. Ship Apache and PHP logs to CloudWatch Logs (/lostfound/httpd, 7-day retention).
#    Optional: runs last and never stops the web server if it fails.
set +e
dnf install -y amazon-cloudwatch-agent
TOKEN=$(curl -s -X PUT http://169.254.169.254/latest/api/token \
        -H "X-aws-ec2-metadata-token-ttl-seconds: 60")
INSTANCE_ID=$(curl -s -H "X-aws-ec2-metadata-token: $TOKEN" \
        http://169.254.169.254/latest/meta-data/instance-id)
cat > /opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json <<CWCONFIG
{
  "logs": {
    "logs_collected": {
      "files": {
        "collect_list": [
          {"file_path": "/var/log/httpd/access_log", "log_group_name": "/lostfound/httpd",
           "log_stream_name": "$INSTANCE_ID/access", "retention_in_days": 7},
          {"file_path": "/var/log/httpd/error_log", "log_group_name": "/lostfound/httpd",
           "log_stream_name": "$INSTANCE_ID/error", "retention_in_days": 7},
          {"file_path": "/var/log/php-fpm/www-error.log", "log_group_name": "/lostfound/httpd",
           "log_stream_name": "$INSTANCE_ID/php-error", "retention_in_days": 7}
        ]
      }
    }
  }
}
CWCONFIG
/opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl -a fetch-config -m ec2 -s \
  -c file:/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json
echo "CloudWatch agent exit code: $?"
