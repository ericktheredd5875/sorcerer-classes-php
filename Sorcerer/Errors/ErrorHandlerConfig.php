<?php

if(!defined("ERR_DB_HOST"))
{
    define("ERR_DB_HOST", "10.209.97.159"); // RS::MasterMother
}

if(!defined("ERR_DB_USERNAME"))
{   define("ERR_DB_USERNAME",   "error_admin");     }

if(!defined("ERR_DB_PASSWORD"))
{   define("ERR_DB_PASSWORD",   "z@p@t0s_@r3_sh03s");     }

if(!defined("ERR_DB_DATABASE"))
{   define("ERR_DB_DATABASE",   "idx_master");     }

if(!defined("ERR_EMAIL_FROM"))
{   define("ERR_EMAIL_FROM",   "ericktheredd5875@gmail.com");     }


// Should be: DEV or LIVE;
if(!defined("ERR_HANDLER_MODE"))
{   define("ERR_HANDLER_MODE", "DEV");      }

/*

GRANT USAGE ON *.* TO 'error_admin'@'10.181.233.48' IDENTIFIED BY PASSWORD 'z@p@t0s_@r3_sh03s';
GRANT SELECT, INSERT, UPDATE, DELETE ON `idx_error_log`.* TO 'error_admin'@'10.181.233.48';


GRANT USAGE ON *.* TO 'error_admin'@'10.181.224.35' IDENTIFIED BY PASSWORD 'z@p@t0s_@r3_sh03s';
GRANT SELECT, INSERT, UPDATE, DELETE ON `idx_error_log`.* TO 'error_admin'@'10.181.224.35';


mysqldump -h 10.181.230.73 -uroot -pGmT8s76k4paL2oE idx_error_log > ~/transition-to-master-mother.sql
mysql -h 10.209.97.159 -uroot -pGmT8s76k4paL2oE -r idx_error_log < ~/transition-to-master-mother.sql

CREATE TABLE idx_master.error_log LIKE idx_error_log.errors
INSERT INTO idx_master.errors (SELECT * FROM idx_error_log.errors)

 */