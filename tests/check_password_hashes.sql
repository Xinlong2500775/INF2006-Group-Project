-- S17: confirm no plain-text passwords are stored.
-- Run against the app database (phpMyAdmin SQL tab, or the mysql client):
--   mysql -h <db-host> -u <user> -p inf2006 < tests/check_password_hashes.sql
-- PASS if bcrypt_hashes = total_users and shortest_hash = 60.

SELECT
    COUNT(*)                                        AS total_users,
    SUM(password_hash REGEXP '^\\$2[aby]\\$[0-9]{2}\\$') AS bcrypt_hashes,
    MIN(CHAR_LENGTH(password_hash))                 AS shortest_hash,
    MAX(CHAR_LENGTH(password_hash))                 AS longest_hash
FROM users;
