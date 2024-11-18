<?php
/**
 * undocumented class
 *
 * @package default
 * @author 	Eric Harris <ericktheredd5875@gmail.com>
 *
 **/

namespace Sorcerer\Logging;

class LogWriter
{
	/*=======================================
	=            Legacy Dividers            =
	=======================================*/
	const ERROR_DIVIDER = "+ ---------------------- !!! ERROR  !!! ---------------------- +";
	const LONG_DIVIDER  = "+ ------------------------------------------------------------ +";
	const SHORT_DIVIDER = "+ ------------------------------ +";
	/*=====  End of Legacy Dividers  ======*/

	/*===========================================
	=            New School Dividers            =
	===========================================*/
	// ERROR Divider: 70 Char
	const DIVIDER_E = "++------------------------- !!! ERROR  !!! -------------------------++";
	// Long Length Divider: 70 char
	const DIVIDER_L = "++------------------------------------------------------------------++";
	// Medium Length Divider: 50 char
	const DIVIDER_M = "++----------------------------------------------++";
	// Short Length Divider: 30 Char
	const DIVIDER_S = "++--------------------------++";
	/*=====  End of New School Dividers  ======*/

	// Bash Colors: http://misc.flogisoft.com/bash/tip_colors_and_formatting
	const BASH_CLR_RESET = "\033[0m";

	/**
	 * Default Log Retention TimeFrame:
	 * 14 Days / 2 Weeks
	 */
	const LOG_RETENTION = 14;

	const LOG_SUFFIX = ".log.txt";

	private static $instance;
	private static $pre_log = array();

	private $use_header_footer;
	private $write_to_console;

	private $_log_file_date;
	private $_log_file_basename;
	private $_log_file_name;
	private $_log_title;

	private $_log_handle;

	private $automatic_cleanup   = true;
	private $retention           = 0;
	private $retention_date      = "";
	private $retention_unix      = "";
	private $retention_del_count = 0;

	/**
	 * [__construct description]
	 */
	function __construct($to_console = false)
	{
		$this->setUseHeaderFooter()
			->setConsoleFlag()
			->setLogRetention("");

		self::$instance = $this;

		return $this;
	}

	/*===================================
	=            Set Methods            =
	===================================*/
	/**
	 * Collects Log Entries that are generated before the
	 * Log File has been opened and is ready to be written to.
	 *
	 * This is a STATIC method, allowing it's use before the LogWriter
	 * object is available for writing.
	 * @static
	 * @param mixed $_data Defaults to (empty) "", which generates nothing.
	 *                     If $_data is a string, then Log Type is assumed
	 *                     to be "INFO". Generating an "INFO" level log entry.
	 *                     If $_data is an array, this can take several forms.
	 *                     1. If the array value is an array (See Example 1),
	 *                     the Log Type must be the array key ("INFO")
	 *                     and the Log Entry must be the array
	 *                     value ("Log Detail").
	 *                     2. If the array value is a string (See Example 2),
	 *                     then the Log Type is assumed to be "INFO" and will
	 *                     generate an "INFO" level log entry.
	 *
	 * @example (array) 1. $_data[] = array("INFO" => "Log Detail");
	 *          		   $_data[] = array("DEBUG" => "Log Detail");
	 *          		   $_data[] = array("ERROR" => "Log Detail");
	 *          		2. $_data[] = "Log Detail";
	 */
	public static function setPreLogEntries($_data = "")
	{
		if(empty($_data)) { return true; }

		if(is_array($_data))
		{
			$_data_count = count($_data);
			if(0 < $_data_count)
			{
				foreach($_data as $d_key => $d_value)
				{
					if(is_array($d_value))
					{
						list($log_type, $log_entry) = each($d_value);
						$log_type = strtoupper($log_type);
					}
					else
					{
						$log_type  = "INFO";
						$log_entry = $d_value;
					}

					self::$pre_log[][$log_type] = $log_entry;
				}
			}
		}
		else
		{	self::$pre_log[]["INFO"] = $_data;	}

		return true;
	}

	public function setConsoleFlag($_data = true)
	{
		$this->write_to_console = $_data;
		return $this;
	}

	/**
	 * [setLogFileName description]
	 * @param string $log_file [description]
	 */
	public function setLogFileName($log_file = "")
	{
		$this->_log_file_date     = date("Y-m-d");
		$this->_log_file_basename = basename($log_file);

		$this->_log_file_name     = $log_file . date("Y-m-d") . ".log.txt";
		return $this;
	}

	/**
	 * Turns On/Off the automatic use of Headers and Footers.
	 *
	 * ON: Useful for Logging a Cron or Process that has a
	 * 		defined beginning and end. Usually handled by a single agent.
	 *
	 * Off: Useful when generating a running Log.
	 * 		Such as user actions or query use. Things that might have
	 * 		multiple interactive agents.
	 *
	 * @param boolean $_data Defaults to true, making the use of
	 *                       Headers and Footers on by default.
	 */
	public function setUseHeaderFooter($_data = true)
	{
		$this->use_header_footer = $_data;
		return $this;
	}

	/**
	 * Sets the Log Title used in generating the Header/Footer of the
	 * Log instance.
	 *
	 * @param string $_title Title of the Log or Process being Logged.
	 */
	public function setLogTitle($_title = "")
	{
		$this->_log_title = $_title;
		return $this;
	}

	/**
	 * Sets the Automatic Clean-Up flag for Log File Retention.
	 *
	 * @param boolean $auto_clean Defaults to True. False will require the
	 *                            use of the method::logFileCleanup to
	 *                            procceed with Log Clean-Up.
	 */
	public function setAutoCleanup($auto_clean = true)
	{
		$this->automatic_cleanup = $auto_clean;
		return $this;
	}

	/**
	 * Sets the Log Retention time length or Disables the use of Log
	 * Clean-Up.
	 *
	 * @param integer $retention Defaults to 0, which triggers the use
	 *                           of the const::LOG_RETENTION (14 Days).
	 *                           If (boolean) false is given, Log Clean-Up
	 *                           is turned OFF.
	 */
	public function setLogRetention($retention = 0)
	{
		if(false === $retention)
		{
			$this->retention = 0;
			$this->setAutoCleanup(false);
		}
		else if(empty($retention) || !is_int($retention))
		{	$this->retention = self::LOG_RETENTION;	}
		else
		{	$this->retention = $retention;		}

		/*===========================================================
		=            Sets needed Time Interval Variables            =
		===========================================================*/
		if(0 < $this->retention)
		{
			$_dt_interval = "P{$this->retention}D";

			$oldest_keep = new \DateTime();

			if(false === method_exists($oldest_keep, "sub"))
			{	$oldest_keep->modify("- {$this->retention} day");		}
			else
			{	$oldest_keep->sub(new \DateInterval($_dt_interval));		}

			$this->retention_unix = $oldest_keep->format("U");
			$this->retention_date = $oldest_keep->format("c");
		}
		/*=====  End of Sets needed Time Interval Variables  ======*/

		return $this;
	}
	/*-----  End of Set Methods  ------*/

	/*===================================
	=            Get Methods            =
	===================================*/
	public static function getMyLogger()
	{
		if(!isset(self::$instance))
		{
			$_obj = __CLASS__;
			new $_obj;
		}

		return self::$instance;
	}

	public function getRetentionDate()
	{
		return $this->retention_date;
	}

	public function getRetentionDeleteCount()
	{
		return $this->retention_del_count;
	}

	public function getLogFileName()
	{	return $this->_log_file_name;		}

	public static function getLongDivider()
	{
		return self::DIVIDER_L;
	}

	public static function getMediumDivider()
	{
		return self::DIVIDER_M;
	}

	public static function getShortDivider()
	{
		return self::DIVIDER_S;
	}

	public static function getErrorDivider()
	{
		return self::DIVIDER_E;
	}
	/*-----  End of Get Methods  ------*/

	/*=========================================
	=            Log FILE Handlers            =
	=========================================*/
	/**
	 * [openLogFile description]
	 * @return [type] [description]
	 */
	public function openLogFile()
	{
		$this->_log_handle = fopen($this->_log_file_name, "a+");

		if(!empty($this->use_header_footer))
		{	$this->writeLogHeaderFooter("SO: ");		}

		$this->processPreLogEntries();

		return $this;
	}

	/**
	 * [closeLogFile description]
	 * @return [type] [description]
	 */
	public function closeLogFile()
	{
		if(!empty($this->use_header_footer))
		{	$this->writeLogHeaderFooter("EO: ");		}

		fclose($this->_log_handle);
		return $this;
	}

	/*-----  End of Log FILE Handlers  ------*/


	/*=======================================
	=            Logging Methods            =
	=======================================*/
	private function processPreLogEntries()
	{
		$_pre_log = self::$pre_log;
		$_pre_log_count = count($_pre_log);
		if(0 < $_pre_log_count)
		{
			foreach($_pre_log as $pl_key => $pl_value)
			{
				list($log_type, $log_entry) = each($pl_value);
				if("INFO" === $log_type)
				{
					$this->logInfo($log_entry);
				}
				else if("DEBUG" === $log_type)
				{
					$this->logDebug($log_entry);
				}
				else if("ERROR" === $log_type)
				{
					$this->logError($log_entry);
				}
			}
		}

		return $this;
	}

	private function writeLogHeaderFooter($prefix = "")
	{
		echo "\033[1;32m";

		$_log_msg = "++--- {$prefix}"
					. "{$this->_log_title}: "
					. date("m-d-Y H:i:s");

		$this->logLongDivider()
			->logInfo($_log_msg)
			->logLongDivider();

		echo "\033[0m";

		return $this;
	}

	public function logLongDivider($bash_color = "")
	{
		if(!empty($bash_color))
		{
			echo $bash_color;
			$this->logInfo(self::DIVIDER_L);
			echo self::BASH_CLR_RESET;

			return $this;
		}

		return $this->logInfo(self::DIVIDER_L);
	}

	public function logMediumDivider()
	{
		return $this->logInfo(self::DIVIDER_M);
	}

	public function logShortDivider()
	{
		return $this->logInfo(self::DIVIDER_S);
	}

	public function logErrorDivider()
	{
		return $this->logInfo(self::DIVIDER_E);
	}

	public function logLabeledDivider($label)
	{
		$_label_divider = "++----------++ "
						. $label
						. " ++----------++";

		return $this->logInfo($_label_divider);
	}

	// I: Info
	public function writeInfo($_msg)
	{
		return $this->logInfo($_msg);
	}

	// Switch Method: writeInfo() to logInfo()
	public function logInfo($_msg)
	{
		return $this->writeLogFile($_msg);
	}

	public function INFO($_msg)
	{
		return $this->logInfo($_msg);
	}

	// D: Debug
	public function logDebug($_msg)
	{
		$_msg = ":[DEBUG]{$_msg}";
		return $this->writeLogFile($_msg);
	}

	// Alias of logDebug
	public function WARN($_msg)
	{
		$_msg = ":[WARN]{$_msg}";
		return $this->writeLogFile($_msg);
	}

	public function ERROR($_msg)
	{
		return $this->writeLogFile($_msg);
	}

	// E: Error
	public function logError($_msg)
	{
		$_msg = ":[ERROR]{$_msg}";
		//return $this->writeLogFile($_msg);

		$_log_msg = date("[Y-m-d H:i:s]") . "{$_msg}";
		fwrite($this->_log_handle,  "{$_log_msg}" . PHP_EOL);

		$_log_msg = date("[H:i:s]") . "{$_msg}";
		//echo "\033[1;31m{$_log_msg}\033[0m" . PHP_EOL;
		echo "{$_log_msg}" . PHP_EOL;

		return $this;
	}

	// Takes an Array were:
	// Key 	 = Type (code, msg, details)
	// Value = Info associated with the Key (code #, msg string)
	public function logErrorBlock(array $_msg, $level_prefix = "++")
	{
		echo "\033[1;31m";
		if(!is_array($_msg))
		{
			return $this->logError(__CLASS__
										. "::"
										. __METHOD__
										. ": requires 1st Parameter to be "
										. "an array!");
		}
		else
		{
			$msg_count = count($_msg);
			if(0 < $msg_count)
			{
				$this->logLongDivider()->logErrorDivider();

				if(!empty($_msg["title"]))
				{
					$msg_txt = "{$level_prefix} {$_msg["title"]}";
					$this->logError($msg_txt)
						->logShortDivider();
				}

				foreach($_msg AS $key => $value)
				{
					if("title" === $key)
					{
						continue;
					}

					$key = strtoupper($key);
					if(is_array($value))
					{
						$msg_txt = "{$level_prefix} {$key}::";
						$this->logError($msg_txt);

						$_prefix = $level_prefix . "+";
						$this->logErrorBlock($value, $_prefix);
					}
					else
					{
						$msg_txt = "{$level_prefix} {$key}: {$value}";
						$this->logError($msg_txt);
					}
				}

				$this->logErrorDivider()->logLongDivider();
			}
		}

		echo "\033[0m";

		return $this;
	}

	/**
	 * [writeLogFile description]
	 * @param  [type]  $_in        [description]
	 * @param  boolean $to_console [description]
	 * @return [type]              [description]
	 */
	public function writeLogFile($_msg, $to_console = false, $log_type = "S")
	{
		if("E" === $log_type)
		{
			return $this->logError($_msg);
			//$_msg = ":[ERROR] {$_msg}";
		}

		$_log_msg = date("[Y-m-d H:i:s]") . "{$_msg}";
		fwrite($this->_log_handle,  "{$_log_msg}" . PHP_EOL);

		if($to_console)
		{	$this->writeToConsole($_msg); 		}
		else if($this->write_to_console)
		{	$this->writeToConsole($_msg);		}

		return $this;
	}

	/**
	 * [writeToConsole description]
	 * @param  [type] $_in [description]
	 * @return [type]      [description]
	 */
	private function writeToConsole($_msg)
	{
		$_log_msg = date("[H:i:s]") . "{$_msg}";
		echo "{$_log_msg}" . PHP_EOL;

		return $this;
	}

	/*
	$_error_in = array();
	$_error_in['msg']   = "Error-Message";
	$_error_in['url'] = "DOMAIN-URL";
	$_error_in['type'] = "rets";
	$_error_in['code'] = "20202";
	$_error_in['text'] = "Invalid select on field [Select] Select Field [**] does not exist in the Class [**].";

	$_errors = array();
	$_errors['type']      = "Error-Type";
	$_errors['msg']       = "++ !ERROR! {$_error_info['msg']}<br>"
							. "++ ERROR Type: {$_error_info['type']}<br>"
							. "++ ERROR Code: {$_error_info['code']}<br>"
							. "++ ERROR Msg:  {$_error_info['text']}<br>"
							. "++ RETS URL:   {$_error_info['query']}";
	 */
	public function writeError(array $_error_in, $to_console = false)
	{
		echo "+ writeError method needs update!!!\n";
		print_r($_error_in);
		echo "\n\n";
		return $this;


		if(!empty($_error_in['threshold'])) { return $this; }

		$this->writeLogFile('+', $to_console)
			->writeLogFile(self::DIVIDER_L, $to_console)
			->writeLogFile(self::DIVIDER_E, $to_console)
			->writeLogFile('++', $to_console);

		if(!empty($_error_in['msg']['title']))
		{
			// 9 Characters :: "E:TYPE-->"
			$this->writeLogFile("++ !ERROR!-> {$_error_in['msg']['title']}", $to_console)
				->writeLogFile("++ E:TYPE--> {$_error_in['type']}", $to_console);

			$msg_count = count($_error_in['msg']['body']);
			if($msg_count > 0)
			{
				$body_msg = $_error_in['msg']['body'];
				foreach($body_msg AS $key => $value)
				{	if(!empty($value)) { $this->writeLogFile("++ E:MSG---> {$value}", $to_console); }	}
			}
		}
		else
		{
			// 9 Characters :: "E:TYPE-->"
			$this->writeLogFile("++ !ERROR!-> {$_error_in['msg']}", $to_console);

			if(!empty($_error_in['type']))
			{	$this->writeLogFile("++ E:TYPE--> {$_error_in['type']}", $to_console);	}

			if(!empty($_error_in['code']))
			{	$this->writeLogFile("++ E:CODE--> {$_error_in['code']}", $to_console);	}

			if(!empty($_error_in['text']))
			{	$this->writeLogFile("++ E:TEXT--> {$_error_in['text']}", $to_console);	}

			if(!empty($_error_in['query']))
			{	$this->writeLogFile("++ E:QUERY->  {$_error_in['query']}", $to_console);	}
		}

		if(!empty($_error_in['stack']))
		{
			$this->writeLogFile("++ E:STACK-> ", $to_console)
				->writeLogFile($_error_in['stack'], $to_console);
		}

		$this->writeLogFile('++', $to_console)
			->writeLogFile(self::DIVIDER_E, $to_console)
			->writeLogFile(self::DIVIDER_L, $to_console)
			->writeLogFile('+', $to_console);

		return $this;
	}
	/*-----  End of Logging Methods  ------*/

	/*=========================================
	=            Log File Clean-Up            =
	=========================================*/
	public function logFileCleanup()
	{
		if(0 < $this->retention)
		{
			$this->buildDeleteList();
		}
		else
		{
			$this->logLongDivider()
				->logLabeledDivider("Log File Cleanup")
				->INFO("+ Retention Days --> OFF")
				->INFO("+ Log Cleanup is turned OFF")
				->logLongDivider();
		}

		return $this;
	}

	/*
	private $retention_date      = "";
	private $retention_unix      = "";
	private $retention_del_count = 0;

	$this->_log_file_date     = date("m-d-Y");
	$this->_log_file_basename = basename($log_file);

	$this->_log_file_name     = $log_file . date("m-d-Y") . ".log.txt";
	 */
	private function buildDeleteList()
	{
		$total_delete_size = 0;
		$log_dir = dirname($this->_log_file_name);
		$clean_out_pieces = array($this->_log_file_basename, ".log.txt", ".log");

		$this->logLongDivider()
			->logLabeledDivider("Log File Cleanup")
			->INFO("+ Retention Days --> " . $this->retention)
			->INFO("+ Retention Date --> " . $this->retention_date)
			->INFO("+ Retention Unix --> " . $this->retention_unix)
			->logShortDivider()
			->INFO("+ LOG DIRECTORY ---> " . $log_dir)
			->INFO("+ LOG File Base ---> " . $this->_log_file_basename);

		$dir_files  = scandir($log_dir);
	    if($dir_files[0] == '.')    { unset($dir_files[0]); }
	    if($dir_files[1] == '..')   { unset($dir_files[1]); }

	    $file_count = count($dir_files);
	    $this->INFO("+ LOG DIR Count ---> " . $file_count)
			->logMediumDivider();

	    if(0 < $file_count)
	    {
	    	foreach($dir_files as $f_key => $file)
	    	{
	    		if(false !== stripos($file, $this->_log_file_basename))
	    		{
					$msg               = "";
					$abs_file_path     = $log_dir . "/" . $file;
					$current_file_size = filesize($abs_file_path);

	    			$file_name_chunk = str_ireplace($clean_out_pieces, "", $file);
	    			if(2 === strpos($file_name_chunk, "-"))
	    			{	$file_unix = $this->convertLogDate($file_name_chunk);	}
	    			else
	    			{	$file_unix = strtotime($file_name_chunk);				}

	    			if(empty($file_unix))
	    			{
						$file_stats = stat($abs_file_path);
						$file_unix  = $file_stats["mtime"];
	    			}

	    			if((int) $this->retention_unix > (int) $file_unix)
	    			{
	    				if(!@unlink($abs_file_path))
	                    {
	                    	$msg = "+ [DELETE] ---> Failed  :=: "
	                    			. "(F: {$file} / "
	                    			. "S: "
	                    			. $this->formatFileSize($current_file_size)
	                    			. ")";
	                    }
	                    else
	                    {
	                        $msg = "+ [DELETE] ---> Success :=: "
	                    			. "(F: {$file} / "
	                    			. "S: "
	                    			. $this->formatFileSize($current_file_size)
	                    			. ")";


	                    }

	                    $total_delete_size += $current_file_size;
	                    $this->retention_del_count++;
	    			}
	    			else
	    			{
	    				$msg = "+ [-SAVE-] ---> {$file} :=: "
	                    			. "("
	                    			. "S: "
	                    			. $this->formatFileSize($current_file_size)
	                    			. ")";
	    			}

	    			$this->INFO($msg);
	    		}
	    	}
	    }

	    if(0 < (int) $this->retention_del_count)
	    {
	    	$this->logMediumDivider()
	    		->INFO('+ [DEL] # of Files > ' . $this->retention_del_count)
	        	->INFO('+ [DEL] Total Size > ' . $this->formatFileSize($total_delete_size))
	        	->logMediumDivider();
	    }
	    else
	    {
	    	$this->logMediumDivider()
	    		->INFO('+ NO Log Files to Delete');
	    }

	   	$this->logLongDivider();

	    return $this;
	}

	private function formatFileSize($bytes)
	{
	   if ($bytes < 1024) return $bytes.' B';
	   elseif ($bytes < 1048576) return round($bytes / 1024, 2).' KB';
	   elseif ($bytes < 1073741824) return round($bytes / 1048576, 2).' MB';
	   elseif ($bytes < 1099511627776) return round($bytes / 1073741824, 2).' GB';
	   else return round($bytes / 1099511627776, 2).' TB';
	}

	private function convertLogDate($date_string)
	{
	    if(false !== strpos($date_string, "-"))
	    {	$date_string = str_replace("-", "", $date_string);		}

	    $file_month = substr($date_string, 0, 2);
	    $file_day   = substr($date_string, 2, 2);
	    $file_year  = substr($date_string, 4);

	    return strtotime($file_year . $file_month . $file_day);
	}

	public function formatTimeStamp($seconds_input)
	{
	    $seconds_input    = number_format(floatval($seconds_input), 5, '.', '');
	    list($sec, $usec) = explode('.', $seconds_input);
	    $minutes_whole    = (int) ($sec / 60); // Whole Minutes -- Used for Calculations Only

		$milliseconds = (int) ($usec);
		$seconds      = (int) ($sec % 60);
		$minutes      = (int) ($minutes_whole % 60);
		$hours        = (int) ($minutes_whole / 60);

		if($hours   < 10) 	{ $hours   = '0' . $hours;   }
		if($minutes < 10)	{ $minutes = '0' . $minutes; }
		if($seconds < 10) 	{ $seconds = '0' . $seconds; }
		$time_stamp = "{$hours}:{$minutes}:{$seconds}.{$usec}";

		return $time_stamp;
	}
	/*=====  End of Log File Clean-Up  ======*/



	/**
	 * [__destruct description]
	 */
	function __destruct()
	{
		if(is_resource($this->_log_handle)) { $this->closeLogFile(); }
	}
} // END class
/*-----  End of LogWriter Class  ------*/