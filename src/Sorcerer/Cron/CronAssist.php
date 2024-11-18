<?php
/**
*
*/
namespace Sorcerer\Cron;

use Sorcerer\Cron\CronAssistException as CAException, 
	Sorcerer\Pdo\PdoDatabase as PdoDB;

class CronAssist
{
	/*=========================================
	=            CronAssist Params            =
	=========================================*/
	const DB_CONN_NAME            = "CronAssist";
	const LOCK_FILE_DIR           = "/var/www/common/cronassist/lock_files/";
	const LOCK_FILE_SUFFIX        = ".lock";
	
	private static $lock_file_dir = "";
	private static $cron_log_id   = 0;
	private static $script_pid    = 0;
	private static $cron_script   = "";
	private static $lock_details  = array();
	private static $stranded_lock = false;
	
	private static $end_dts       = "";
	
	private static $db = "";
	private static $db_all_good   = false;
	private static $cur_db_conn   = "";
	/*=====  End of CronAssist Params  ======*/

	function __construct() 	{		}

	function __clone() 		{		}

	// public static function setDbObj(Sorcerer\Pdo\PdoDatabase $db)
	// {
	// 	self::$db          = $db;
	// 	self::$db_all_good = true;
	// 	self::$cur_db_conn = "";
	// }

	public static function setLockFileDir(string $dir): bool
	{
		if(is_dir($dir))
		{
			if(is_writable($dir))
			{	self::$lock_file_dir = $dir;	}
			else
			{	throw New CAException("'{$dir}' is NOT Writable");	}
		}
		else
		{	throw new CAException("'{$dir}' is NOT a Directory");		}

		return true;
	}

	public static function getCronLogId()
	{
		return self::$cron_log_id;
	}

	public static function getStrandedLock()
	{
		return self::$stranded_lock;
	}

	public static function getLockDetails()
	{
		return self::$lock_details;
	}

	public static function getEndDTS()
	{
		return self::$end_dts;
	}

	private static function cleanFileName($file_name)
	{
		return str_ireplace(array(":", ",", "_"), "-", $file_name);
	}

	private static function isRunning($script_name, $max_used_depth)
	{
		// "." $13 "." $14 "." $15 "." $16}\'';
		$shell_cmd = 'ps aux | '
					. 'grep ' . $script_name . ' | '
					. 'awk \'{print '
					. '$2 " " $12';

		if(0 < $max_used_depth)
		{
			$column_num = 12;
			for($i = 1; $i <= $max_used_depth; $i++)
			{
				$column_num++;
				$shell_cmd .= '"." $' . $column_num;
			}
		}

		$shell_cmd .= '}\'';

		$processes = `{$shell_cmd}`;
		$_pids = explode(PHP_EOL, $processes);

		$_pid_count = count($_pids);
		if(0 < $_pid_count)
		{
			foreach($_pids as $key => $value)
			{
				if(!empty($value))
				{
					$process = explode(" ", $value);
					if(!empty($process[0])
						&& self::$script_pid === (int) $process[0])
					{
						$base_cmd = basename($process[1]);
						if($script_name === $base_cmd)
						{	return true;		}
					}
				}
			}
		}

		/*
		$_pids = explode(PHP_EOL, `ps -e | awk '{print $1}'`);
		if(in_array(self::$script_pid, $_pids))
		{	return true;	}
		*/

		return false;
	}

	public static function lockCron($_db = "", $max_arg_depth = 4)
	{
		GLOBAL $argv;

		if(empty(self::$lock_file_dir))
		{	self::setLockFileDir(self::LOCK_FILE_DIR);		}

		$_lock_details  = array();
		$max_used_depth = 0;
		$arg_count      = count($argv);
		$script_name    = basename($argv[0]);

		if(1 < $arg_count
			&& 0 < $max_arg_depth)
		{
			for($i = 1; $i <= $max_arg_depth; $i++)
			{
				if(!empty($argv[$i]))
				{
					$arg_value = trim($argv[$i]);
					$script_name .= ".{$arg_value}";
					$max_used_depth++;
				}
			}
		}

		$clean_file_name   = self::cleanFileName($script_name);
		self::$cron_script = $clean_file_name;

		// self::$lock_file_dir
		//$lock_file         = self::LOCK_FILE_DIR
		$lock_file         = self::$lock_file_dir
							. $clean_file_name
							. self::LOCK_FILE_SUFFIX;

		if(file_exists($lock_file))
		{
			$_lock_details      = file_get_contents($lock_file);
			self::$lock_details = json_decode($_lock_details);
			self::$script_pid   = self::$lock_details->PID;

			if(self::isRunning($script_name, $max_used_depth))
			{
				self::$lock_details->MSG = "Lock Failed --> "
										. "Script currently Running";
				return FALSE;
			}
			else
			{
				$lock_msg = "Script DIED without Completion";
				if("Sorcerer\Pdo\PdoDatabase" === get_class($_db))
				{
					$_lock_details = (array) self::$lock_details;
					$_lock_details["MSG"] = $lock_msg;
					self::logCronToDb("inactive", $_db, $_lock_details);
				}

				$lock_dts = self::$lock_details->DTS;
				$lock_msg = "=="
							. self::$script_pid
							. "== {$lock_msg} "
							. "[S: {$lock_dts}]";
				self::$stranded_lock = $lock_msg;
			}
		}

		self::$script_pid = getmypid();
		$_lock_details = array(
					"PID"    => self::$script_pid,
					"SCRIPT" => $clean_file_name,
					"MSG"    => "Lock Acquired > Script Started",
					"ARGV"   => $argv,
					"DTS"    => date("c"),
				);

		file_put_contents($lock_file, json_encode($_lock_details));
		if("Sorcerer\Pdo\PdoDatabase" === get_class($_db))
		{	self::logCronToDb("create", $_db, $_lock_details);	}

		self::$lock_details = (object) $_lock_details;
		return self::$script_pid;
	}

	public static function unLockCron($_db = "")
	{
		GLOBAL $argv;

		$script_file_name = self::$cron_script;
		//$lock_file = self::LOCK_FILE_DIR
		$lock_file = self::$lock_file_dir
						. $script_file_name
						. self::LOCK_FILE_SUFFIX;

		if(file_exists($lock_file))
		{
			if(!unlink($lock_file))
			{
				echo PHP_EOL
					. "++ FAILED to clear Lock File!"
					. PHP_EOL . PHP_EOL;
			}
		}
		else
		{
			echo PHP_EOL
					. "++ Cannot find the LOCK File! :: {$lock_file}"
					. PHP_EOL . PHP_EOL;
		}

		$lock_msg = "Lock Released > Script Completed";
		if(!empty($_db) && "Sorcerer\Pdo\PdoDatabase" === get_class($_db))
		{
			$_lock_details = (array) self::$lock_details;
			$_lock_details["MSG"] = $lock_msg;
			self::logCronToDb("update", $_db, $_lock_details);
		}

		$lock_msg = "==" . self::$script_pid . "== {$lock_msg}";
		return $lock_msg;
	}

	// http://php.net/manual/en/dateinterval.construct.php
	// Defaults to 1 Hour
	public static function shouldCronDie($max_script_time = "PT1H")
	{
		$current_dts    = new \DateTime();
		$cur_unix_dts   = $current_dts->format("U");

		$cron_start_dts = new \DateTime(self::$lock_details->DTS);
		$cron_start_dts->add(new \DateInterval($max_script_time));
		$cron_unix_dts   = $cron_start_dts->format("U");
		$looking_for_dts = $cron_start_dts->format("c");

		self::$end_dts = $looking_for_dts;

		//echo "Cron Start DTS: " . self::$lock_details->DTS . PHP_EOL;
		//echo "Looking 4 DTS:  {$looking_for_dts}" . PHP_EOL;

		if($cur_unix_dts >= $cron_unix_dts)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	private static function checkDbOption($_db)
	{
		$db_all_good           = false;
		$current_db_connection = "";
		if(!empty($_db))
		{
			if("Sorcerer\Pdo\PdoDatabase" !== get_class($_db))
			{	self::failedDbOption();		}
			else
			{
				$current_db_connection = $_db->getActiveConnection();
				if($current_db_connection !== self::DB_CONN_NAME)
				{
					if(false === $_db->checkForCredentials(self::DB_CONN_NAME))
					{	self::failedDbOption();		}
				}
			}

			$db_all_good = true;
		}

		self::$cur_db_conn = $current_db_connection;
		self::$db_all_good = $db_all_good;

		return $db_all_good;
	}

	private static function failedDbOption()
	{
		$_msg = "\033[1;31m"
			. "++ To use DB functionality.. " . PHP_EOL
			. "\t+ Sorcerer\Pdo\PdoDatabase class is Required." . PHP_EOL
			. "\t+ Connection to DB must be set." . PHP_EOL
			. "\t+ Connection to DB should be named: "
			. self::DB_CONN_NAME
			. "\033[0m" . PHP_EOL . PHP_EOL;
		die($_msg);
	}

	private static function logCronToDb($action, $_db, $_lock_details = "")
	{
		if(false === self::$db_all_good)
		{	self::checkDbOption($_db);		}

		self::$cur_db_conn = $_db->getActiveConnection();
		if(self::$cur_db_conn !== self::DB_CONN_NAME)
		{	$_db->setActiveConnection(self::DB_CONN_NAME);		}

		if("create" === $action)
		{
			$cron_log_dets = array(
							"cron_pid"     => self::$script_pid,
							"cron_script"  => $_lock_details["SCRIPT"],
							"cron_details" => json_encode($_lock_details),
						);
			self::$cron_log_id = $_db->insertRecords("cron_log", $cron_log_dets);

			// $sql = "INSERT INTO cron_log
			// 			SET cron_pid = :cron_pid,
			// 			cron_script = :cron_script,
			// 			cron_details = :cron_details,
			// 			cron_start_dts = NOW()";
			// $_db->setQuery($sql)
			// 	->setBinding(":cron_pid", 		self::$script_pid)
			// 	->setBinding(":cron_script", 	$_lock_details["SCRIPT"])
			// 	->setBinding(":cron_details", 	json_encode($_lock_details));
			// self::$cron_log_id = $_db->getResultSet("insert");

			$response = self::$cron_log_id;
		}
		else if("update" === $action)
		{
			$cron_log_dets = array(
							"cron_msg"     => $_lock_details["MSG"],
							"cron_end_dts" => date("c"),
							"cron_active"  => 0,
						);

			$conditions = array(
							"cron_pid"    => self::$script_pid,
							"cron_script" => $_lock_details["SCRIPT"],
							"cron_active" => 1,
						);

			$_db->updateRecords("cron_log", $cron_log_dets, $conditions);

			// $sql = "UPDATE cron_log
			// 			SET cron_msg    = :cron_msg,
			// 			cron_end_dts    = NOW(),
			// 			cron_active     = :cron_active
			// 			WHERE cron_pid  = :cron_pid
			// 			AND cron_script = :cron_script
			// 			AND cron_active = :cron_active_cur";
			// $_db->setQuery($sql)
			// 	->setBinding(":cron_msg", 			$_lock_details["MSG"])
			// 	->setBinding(":cron_active", 		0)
			// 	->setBinding(":cron_pid", 			self::$script_pid)
			// 	->setBinding(":cron_script", 		$_lock_details["SCRIPT"])
			// 	->setBinding(":cron_active_cur", 	1);
			// $_db->getResultSet("update");

			$response = true;
		}
		else if("inactive" === $action)
		{
			$cron_log_dets = array(
							"cron_msg"     => $_lock_details["MSG"],
							"cron_active"  => 0,
						);

			$conditions = array(
							"cron_pid"    => self::$script_pid,
							"cron_script" => $_lock_details["SCRIPT"],
							"cron_active" => 1,
						);

			$_db->updateRecords("cron_log", $cron_log_dets, $conditions);

			// $sql = "UPDATE cron_log
			// 			SET cron_msg    = :cron_msg,
			// 			cron_active     = :cron_active
			// 			WHERE cron_pid  = :cron_pid
			// 			AND cron_script = :cron_script
			// 			AND cron_active = :cron_active_cur";
			// $_db->setQuery($sql)
			// 	->setBinding(":cron_msg", 			$_lock_details["MSG"])
			// 	->setBinding(":cron_active", 		0)
			// 	->setBinding(":cron_pid", 			self::$script_pid)
			// 	->setBinding(":cron_script", 		$_lock_details["SCRIPT"])
			// 	->setBinding(":cron_active_cur", 	1);
			// $_db->getResultSet("update");

			$response = true;
		}
		else
		{

		}

		$_db->setActiveConnection(self::$cur_db_conn);

		return $response;
	}

	/*
	$log_info = array(
		// <= 50 Char field used to Search Against
		"info_key" => "a-key-used-to-search-against",
		// Text Field to store any kind of info. (IS JSON Encoded)
		"info_value" => "json_encoded TEXT Field",
		// <= 200 Char field for a MSG. Can be used with or without the
		// 'info_value' text field.
		"info_msg" => "200 Char Msg Hold. Use with or without 'info_value'",
		// Start Time in MicroSeconds
		// -- Could be used with End Time for some kind of metrics
		"start_time" => Float,
		// End Time in MicroSeconds
		// -- Could be used with  Start Time for some kind of metrics
		"end_time" => Float,
	);
	*/
	public static function logCronInfo($_db, array $log_info)
	{
		if(false === self::$db_all_good)
		{	self::checkDbOption($_db);		}

		self::$cur_db_conn = $_db->getActiveConnection();
		if(self::$cur_db_conn !== self::DB_CONN_NAME)
		{	$_db->setActiveConnection(self::DB_CONN_NAME);		}

		$info_key   = "";
		$info_value = json_ecode(array(""));
		$info_msg   = "";
		$start_time = 0;
		$end_time   = 0;

		if(!empty($log_info["info_key"]))
		{	$info_key = $log_info["info_key"];	}

		if(!empty($log_info["info_value"]))
		{	$info_value = json_encode($log_info["info_value"]);	}

		if(!empty($log_info["info_msg"]))
		{	$info_msg = $log_info["info_msg"];	}

		if(!empty($log_info["start_time"]))
		{	$start_time = $log_info["start_time"];	}

		if(!empty($log_info["end_time"]))
		{	$end_time = $log_info["end_time"];	}

		$cron_log_dets = array(
						"cron_log_id" => self::$cron_log_id,
						"cron_pid"    => self::$script_pid,
						"cron_script" => self::$cron_script,
						"info_key"    => $info_key,
						"info_value"  => $info_value,
						"info_msg"    => $info_msg,
						"start_time"  => $start_time,
						"end_time"    => $end_time,
					);
		$_db->insertRecords("cron_log_info", $cron_log_dets);

		// $sql = "INSERT INTO cron_log_info
		// 			SET cron_log_id = :cron_log_id,
		// 			cron_pid        = :cron_pid,
		// 			cron_script     = :cron_script,
		// 			info_key        = :info_key,
		// 			info_value      = :info_value,
		// 			info_msg        = :info_msg,
		// 			start_time      = :start_time,
		// 			end_time        = :end_time";
		// $_db->setQuery($sql)
		// 	->setBinding(":cron_log_id", 	self::$cron_log_id)
		// 	->setBinding(":cron_pid", 		self::$script_pid)
		// 	->setBinding(":cron_script", 	self::$cron_script)
		// 	->setBinding(":info_key", 		$info_key)
		// 	->setBinding(":info_value", 	$info_value)
		// 	->setBinding(":info_msg", 		$info_msg)
		// 	->setBinding(":start_time", 	$start_time)
		// 	->setBinding(":end_time", 		$end_time);
		// $_db->getResultSet("insert");

		$_db->setActiveConnection(self::$cur_db_conn);
		return true;
	}

}