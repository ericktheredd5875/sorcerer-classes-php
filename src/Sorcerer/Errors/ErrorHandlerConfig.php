<?php

if(!defined("ERR_DB_HOST"))
{
    define("ERR_DB_HOST", "");
}

if(!defined("ERR_DB_USERNAME"))
{   define("ERR_DB_USERNAME",   "error_admin");     }

if(!defined("ERR_DB_PASSWORD"))
{   define("ERR_DB_PASSWORD",   "");     }

if(!defined("ERR_DB_DATABASE"))
{   define("ERR_DB_DATABASE",   "");     }

if(!defined("ERR_EMAIL_FROM"))
{   define("ERR_EMAIL_FROM",   "");     }


// Should be: DEV or LIVE;
if(!defined("ERR_HANDLER_MODE"))
{   define("ERR_HANDLER_MODE", "DEV");      }

/*
GRANT USAGE ON *.* TO '!DB-USER!'@'!IP!' IDENTIFIED BY PASSWORD '!PASSWORD!';
GRANT SELECT, INSERT, UPDATE, DELETE ON `idx_error_log`.* TO '!DB-USER!'@'!IP!';


GRANT USAGE ON *.* TO '!DB-USER!'@'!IP!' IDENTIFIED BY PASSWORD '!PASSWORD!';
GRANT SELECT, INSERT, UPDATE, DELETE ON `idx_error_log`.* TO '!DB-USER!'@'1!IP!';
 */
