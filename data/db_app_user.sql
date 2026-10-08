-- Least-privilege database account for the web app.
--
-- The app only ever reads, inserts and updates rows. It never deletes rows or
-- changes table structure, so it is not given DELETE, DROP, ALTER, CREATE or
-- GRANT. The RDS master account (admin) is used only to run schema.sql and this
-- file, never by the app.
--
-- Run once as the master user AFTER schema.sql, replacing the password:
--   mysql -h <rds-endpoint> -u admin -p inf2006 < data/db_app_user.sql
-- Then put DB_USER=lostfound_app and this password in src/.env (or the EC2 launch script).

CREATE USER IF NOT EXISTS 'lostfound_app'@'%' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';

GRANT SELECT, INSERT, UPDATE ON inf2006.users  TO 'lostfound_app'@'%';
GRANT SELECT, INSERT, UPDATE ON inf2006.items  TO 'lostfound_app'@'%';
GRANT SELECT, INSERT, UPDATE ON inf2006.claims TO 'lostfound_app'@'%';

FLUSH PRIVILEGES;

-- Check: should list only SELECT, INSERT, UPDATE on the three tables
SHOW GRANTS FOR 'lostfound_app'@'%';
