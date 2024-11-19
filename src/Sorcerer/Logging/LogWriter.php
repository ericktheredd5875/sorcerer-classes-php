<?php
/**
 * Log Writer
 * 
 * Handles writing Log Files
 *
 * @package     Sorcerer\Logging\LogWriterException
 * @author      Eric Harris <ericktheredd5875@gmail.com>
 * @copyright   2024 - Eric Harris
 * @version     x.x.x - Comments or Notes
 * @deprecated  x.x.x - Comments or Notes
 */

/*----------  Class Namespacing  ----------*/
namespace Sorcerer\Logging;

/*==================================================
=            Classes Used by this Class            =
==================================================*/
use Sorcerer\Logging\LogWriterExcepton;

use Sorcerer\Core\CoreSingletonTrait;

use Sorcerer\Utilities\Utilities;

use Psr\Log\LogLevel, 
	Psr\Log\LoggerInterface;
/*=====  End of Classes Used by this Class  ======*/

class LogWriter implements LoggerInterface
{
	use CoreSingletonTrait;

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

	private const DIR_SEP = DIRECTORY_SEPARATOR;

	private const DIVIDER_ENDS = "++";
	private const DIVIDER_PAD  = "-";
	private const ERROR_LABEL  = "!!! ERROR !!!";

	
	/**
	 * LONG Divider: 70 Characters = 4 Char (DIVIDER_ENDS*2) + 66 Char (For Padding)
	 */
	private const LNG_DIVIDER_PAD = 76; //66;

	/**
	 * MED Divider: 50 Characters = 4 Char (DIVIDER_ENDS*2) + 46 Char (For Padding)
	 */
	private const MED_DIVIDER_LENGTH = 56; //46;

	/**
	 * SML Divider: 30 Characters = 4 Char (DIVIDER_ENDS*2) + 26 Char (For Padding)
	 */
	private const SML_DIVIDER_LENGTH = 36; //26;

	/**
	 * ! SO: DEPRECATED ITEMS
	 */
	const DIVIDER_E = "++------------------------- !!! ERROR  !!! " 
					. "-------------------------++";
	// Long Length Divider: 70 char
	const DIVIDER_L = "++------------------------------------------------------------------++";
	// Medium Length Divider: 50 char
	const DIVIDER_M = "++----------------------------------------------++";
	// Short Length Divider: 30 Char
	const DIVIDER_S = "++--------------------------++";
	/**
	 * ! EO: DEPRECATED ITEMS
	 */
	/*=====  End of New School Dividers  ======*/

	// Bash Colors: http://misc.flogisoft.com/bash/tip_colors_and_formatting
	private const BASH_CLR_RESET = "\033[0m";

	/**
	 * Default Log Retention TimeFrame:
	 * 14 Days / 2 Weeks
	 */
	private const LOG_RETENTION = 14;

	private const LOG_SUFFIX = ".log.txt";

	/*========================================
    =            Class Attributes            =
    ========================================*/
	
	private static $pre_log_msgs = array();

	// * Formerly >> DIVIDER_E
	private $ERR_DIVIDER = "";
	// * Formerly >> DIVIDER_L
	private $LNG_DIVIDER = "";
	// * Formerly >> DIVIDER_M
	private $MED_DIVIDER = "";
	// * Formerly >> DIVIDER_S
	private $SML_DIVIDER = "";

	private $use_header_footer = true;
	// private $write_to_console  = true;
	private $chatterbox = true;

	// * Formerly >> $_log_file_date
	private $log_date;

	// * Formerly >> $_log_file_basename
	private $log_basename;

	// * Formerly >> $_log_file_name
	private $log_filename;

	private $log_title;

	private $_log_handle;

	private $use_full_dts        = false;
	
	private $automatic_cleanup   = true;
	private $retention           = 0;
	private $retention_date      = "";
	private $retention_unix      = "";
	private $retention_del_count = 0;

	/*=====  End of Class Attributes  ======*/

	function __construct()
	{
		$this->generateDivider("ERR")
			->generateDivider("LNG")
			->generateDivider("MED")
			->generateDivider("SML")
			->setVerbose(true)
			->setUseHeaderFooter(true)
			->setAutoCleanup(true)
			->setLogRetention(self::LOG_RETENTION)
			->setLogDate()
			->setUseFullDts(false);
			
		return $this;
	}

	private function generateDivider(string $divider_type)
	{
		switch ($divider_type) {
			case "ERR":
				$this->ERR_DIVIDER = self::DIVIDER_ENDS
					. str_pad(
						self::ERROR_LABEL,
						self::LNG_DIVIDER_PAD,
						self::DIVIDER_PAD,
						STR_PAD_BOTH
					)
					. self::DIVIDER_ENDS;
				break;
			case "LNG":
				$this->LNG_DIVIDER = self::DIVIDER_ENDS
					. str_pad("", self::LNG_DIVIDER_PAD, self::DIVIDER_PAD)
					. self::DIVIDER_ENDS;
				break;
			case "MED":
				$this->MED_DIVIDER = self::DIVIDER_ENDS
					. str_pad("", self::MED_DIVIDER_LENGTH, self::DIVIDER_PAD)
					. self::DIVIDER_ENDS;
				break;
			case "SML":
				$this->SML_DIVIDER = self::DIVIDER_ENDS
					. str_pad("", self::SML_DIVIDER_LENGTH, self::DIVIDER_PAD)
					. self::DIVIDER_ENDS;
				break;
		}

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
	public static function setPreLogEntries($_data = ""): bool
	{
		$result = true;
        if (!empty($_data)) {
			$log_level = "INFO";
			$log_msg   = $_data;

            if (is_array($_data)) {
				
				$log_msg_count = count($_data);
                if (0 < $log_msg_count) {
                    foreach ($_data as $d_key => $d_value) {
						$log_level = "INFO";
						$log_msg   = $d_value;
                        if (is_array($d_value)) {
                            $log_level     = key($d_value);
                            $log_msg       = $d_value[$log_level];
                        }

						$log_level = strtoupper($log_level);
                        self::setPreLogItem($log_level, $log_msg);
                    }
                }
            } else {
				self::setPreLogItem($log_level, $log_msg);
            }
        }

		return $result;
	}

	private static function setPreLogItem(string $log_level = "INFO", 
										string $log_msg = ""): bool
	{
		$log_level = strtoupper($log_level);
		self::$pre_log_msgs[][$log_level] = $log_msg;

		return true;
	}

	public function setConsoleFlag(bool $flag = true): self
	{
		return $this->setVerbose($flag);
	}

	public function setVerbose(bool $flag = true): self
	{
		$this->chatterbox = $flag;
		return $this;
	}

	public function setUseFullDts(bool $full_dts = false): self
	{
		$this->use_full_dts = $full_dts;
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
	 * @param boolean $flag Defaults to true, making the use of
	 *                       Headers and Footers on by default.
	 */
	public function setUseHeaderFooter(bool $flag = true): self
	{
		$this->use_header_footer = $flag;
		return $this;
	}

	// _log_file_date -> log_date
	private function setLogDate(): self
	{
		$this->log_date = date("Ymd");
		return $this;
	}

	public function setLogFileName(string $filename): self
	{
		return $this->setFileName($filename);
	}

	public function setFileName(string $filename): self
	{
		if(empty($filename))
		{
			$e_msg = "Log Filename cannot be blank.";
			throw new LogWriterException($e_msg);
		}
		
		if(empty($this->log_directory))
		{
			$this->setLogDirectory(dirname($filename));
		}
		
		$this->setLogBasename(basename($filename));
		
		
		$this->log_filename = $this->log_directory
							. $this->log_basename
							. "-"
							. $this->log_date 
							. self::LOG_SUFFIX;
		return $this;
	}

	private function setLogBasename(string $basename): self
	{
		$this->log_basename = $basename;
		return $this;
	}

	public function setLogDirectory(string $directory): self
	{
		$_directory = realpath($directory);		
		// var_dump($_directory);
		if(!is_dir($_directory))
		{
			$e_msg = "Directory '{$directory}' does NOT exist. "
				. "[NOTE: To set the log directory seperately, use "
				. "the 'setLogDirectory' method before the "
				. "'setFileName' method.";
			throw new LogWriterException($e_msg);
		}
		else if(!is_writable($_directory))
		{
			$e_msg = "Directory '{$directory}' is NOT writeable.";
			throw new LogWriterException($e_msg);
		}

		$this->log_directory = $_directory . self::DIR_SEP;	
		
		return $this;
	}

	/**
	 * Sets the Log Title used in generating the Header/Footer of the
	 * Log instance.
	 *
	 * @param string $_title Title of the Log or Process being Logged.
	 */
	public function setLogTitle(string $title = ""): self
	{
		$this->log_title = $title;
		return $this;
	}

	/**
	 * Sets the Automatic Clean-Up flag for Log File Retention.
	 *
	 * @param boolean $auto_clean Defaults to True. False will require the
	 *                            use of the method::logFileCleanup to
	 *                            procceed with Log Clean-Up.
	 */
	public function setAutoCleanup(bool $auto_clean = true): self
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
	public function setLogRetention(int $retention = 0): self
	{
		if(empty($retention))
		{
			$this->retention = 0;
			$this->setAutoCleanup(false);
		}
		else if(0 < $retention)
		{
			$this->retention = $retention;
			$this->setAutoCleanup(true);

			/*===========================================================
			=            Sets needed Time Interval Variables            =
			===========================================================*/
			if (0 < $this->retention) {
				$_dt_interval = "P{$this->retention}D";

				$oldest_keep = new \DateTime();

				if (false === method_exists($oldest_keep, "sub")) {
					$oldest_keep->modify("- {$this->retention} day");
				} else {
					$oldest_keep->sub(new \DateInterval($_dt_interval));
				}

				$this->retention_unix = $oldest_keep->format("U");
				$this->retention_date = $oldest_keep->format("c");
			}
			/*=====  End of Sets needed Time Interval Variables  ======*/
		}
		
		// if(false === $retention)
		// {
		// 	$this->retention = 0;
		// 	$this->setAutoCleanup(false);
		// }
		// else if(empty($retention) || !is_int($retention))
		// {	$this->retention = self::LOG_RETENTION;	}
		// else
		// {	$this->retention = $retention;		}

		/*===========================================================
		=            Sets needed Time Interval Variables            =
		===========================================================*/
		// if(0 < $this->retention)
		// {
		// 	$_dt_interval = "P{$this->retention}D";

		// 	$oldest_keep = new \DateTime();

		// 	if(false === method_exists($oldest_keep, "sub"))
		// 	{	$oldest_keep->modify("- {$this->retention} day");		}
		// 	else
		// 	{	$oldest_keep->sub(new \DateInterval($_dt_interval));		}

		// 	$this->retention_unix = $oldest_keep->format("U");
		// 	$this->retention_date = $oldest_keep->format("c");
		// }
		/*=====  End of Sets needed Time Interval Variables  ======*/

		return $this;
	}
	/*-----  End of Set Methods  ------*/

	/*===================================
	=            Get Methods            =
	===================================*/

	// ! For BACKWARD compatibility.
	public static function getMyLogger()
	{
		return self::getInstance();

		// if(!isset(self::$instance))
		// {
		// 	$_obj = __CLASS__;
		// 	new $_obj;
		// }

		// return self::$instance;
	}


	public function getVerbose(): bool
	{
		return $this->chatterbox;
	}

	public function getUseHeaderFooter(): bool
	{
		return $this->use_header_footer;
	}

	public function getLogTitle(): string
	{
		return $this->log_title;
	}

	public function getLogDate(): string
	{
		return $this->log_date;
	}

	public function getLogBasename(): string
	{
		return $this->log_basename;
	}

	public function getLogFileName(): string
	{
		return $this->log_filename;
	}

	public function getLogDirectory(): string
	{
		return $this->log_directory;
	}

	public function getRetentionDate(): string
	{
		return $this->retention_date;
	}

	public function getRetentionDeleteCount()
	{
		return $this->retention_del_count;
	}

	

	public function getLngDivider()
	{
		return $this->LNG_DIVIDER;
	}
	
	public static function getLongDivider()
	{
		return self::DIVIDER_L;
	}

	public function getMedDivider()
	{
		return $this->MED_DIVIDER;
	}

	public static function getMediumDivider()
	{
		return self::DIVIDER_M;
	}

	public function getSmlDivider(): string
	{
		return $this->SML_DIVIDER;
	}

	public static function getShortDivider(): string
	{
		// return $this->SML_DIVIDER;
		return self::DIVIDER_S;
	}

	public function getErrDivider(): string
	{
		return $this->ERR_DIVIDER;
	}

	public static function getErrorDivider(): string
	{
		// return $this->ERR_DIVIDER;
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
	public function openLogFile(): self
	{
		$this->_log_handle = fopen($this->log_filename, "a+");
		if(false === $this->_log_handle)
		{
			throw new LogWriterException("Log File could not be opened or created. "
						. "[{$this->log_filename}]");
		}

		$this->logHeaderFooter("[START]")
			->processPreLogEntries();

		return $this;
	}

	/**
	 * [closeLogFile description]
	 * @return [type] [description]
	 */
	public function closeLogFile(): self
	{
		$this->logFileCleanup();		
		$this->logHeaderFooter("[-END-]");
		fclose($this->_log_handle);

		return $this;
	}

	/*-----  End of Log FILE Handlers  ------*/

	/*============================================
	=            Debug Logger Methods            =
	============================================*/
	/**
	 * Detailed debug information.
	 *
	 * @param string $message 
	 * 	* NOTE: Accepts a string as the message, or an object with a __toString() method.
	 * @param array  $context
	 *
	 * @return void
	 */
	public function debug($message, array $context = array()): self
	{
		$this->log(LogLevel::DEBUG, $message, $context);
		return $this;
	}

	// ! NOTE: Used only for backward compatibility. Use the 'debug' method.
	public function logDebug(string $message, array $context = array()): self
	{
		$this->log(LogLevel::DEBUG, $message, $context);
		return $this;
	}
	/*=====  End of Debug Logger Methods  ======*/

	/*===========================================
	=            Info Logger Methods            =
	===========================================*/
	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	public function info($message, array $context = array()): self
	{
		$this->log(LogLevel::INFO, $message, $context);
		return $this;
	}

	/**
	 * Informational/Interesting Events
	 * 
	 * IE: User logs in, SQL logs.
	 * 
	 * ! NOTE: Used only for backward compatibility. Use the 'info' method.
	 *
	 * @param string $_msg
	 * @param array $context
	 *
	 * @return self
	 */
	public function writeInfo(string $message, array $context = array()): self
	{
		$this->log(LogLevel::INFO, $message, $context);
		return $this;
	}

	// ! NOTE: Used only for backward compatibility. Use the 'info' method.
	public function logInfo(string $message, array $context = array()): self
	{
		$this->log(LogLevel::INFO, $message, $context);
		return $this;
	}

	// ! NOTE: Used only for backward compatibility. Use the 'info' method.
	// public function INFO(string $message, array $context = array()): self
	// {
	// 	$this->log(LogLevel::INFO, $message, $context);
	// 	return $this;
	// }
	/*=====  End of Info Logger Methods  ======*/

	/*=============================================
	=            Notice Logger Methods            =
	=============================================*/
	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	public function notice($message, array $context = array()): self
	{
		$this->log(LogLevel::NOTICE, $message, $context);
		return $this;
	}
	/*=====  End of Notice Logger Methods  ======*/

	/*==============================================
	=            Warning Logger Methods            =
	==============================================*/
	/**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function warning($message, array $context = array()): self
    {
		$this->log(LogLevel::WARNING, $message, $context);
		return $this;
	}
	
	// ! NOTE: Used only for backward compatibility. Use the 'warning' method.
	public function WARN(string $message, array $context = array()): self
	{
		$this->log(LogLevel::WARNING, $message, $context);
		return $this;
	
		// $_msg = ":[WARN]{$_msg}";
		// return $this->writeLogFile($_msg);
	}
	/*=====  End of Warning Logger Methods  ======*/

	/*============================================
	=            Error Logger Methods            =
	============================================*/
	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	// ! NOTE: Used only for backward compatibility. Use the 'error' method.
	public function error($message, array $context = array()): self
	{
		$this->log(LogLevel::ERROR, $message, $context);
		return $this;
	}

	// E: Error
	public function logError(string $_msg): self
	{
		$this->error($_msg);
		return $this;
		// $_msg = ":[ERROR]{$_msg}";
		// //return $this->writeLogFile($_msg);

		// $_log_msg = date("[Y-m-d H:i:s]") . "{$_msg}";
		// fwrite($this->_log_handle,  "{$_log_msg}" . PHP_EOL);

		// $_log_msg = date("[H:i:s]") . "{$_msg}";
		// //echo "\033[1;31m{$_log_msg}\033[0m" . PHP_EOL;
		// echo "{$_log_msg}" . PHP_EOL;

		// return $this;
	}

	// Takes an Array were:
	// Key 	 = Type (code, msg, details)
	// Value = Info associated with the Key (code #, msg string)
	public function logErrorBlock(array $_msg, $level_prefix = "++")
	{
		echo "\033[1;31m";
		if (!is_array($_msg)) {
			return $this->logError(__CLASS__
				. "::"
				. __METHOD__
				. ": requires 1st Parameter to be "
				. "an array!");
		} else {
			$msg_count = count($_msg);
			if (0 < $msg_count) {
				$this->logLongDivider()->logErrorDivider();

				if (!empty($_msg["title"])) {
					$msg_txt = "{$level_prefix} {$_msg["title"]}";
					$this->logError($msg_txt)
						->logShortDivider();
				}

				foreach ($_msg as $key => $value) {
					if ("title" === $key) {
						continue;
					}

					$key = strtoupper($key);
					if (is_array($value)) {
						$msg_txt = "{$level_prefix} {$key}::";
						$this->logError($msg_txt);

						$_prefix = $level_prefix . "+";
						$this->logErrorBlock($value, $_prefix);
					} else {
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
	/*=====  End of Error Logger Methods  ======*/

	/*===============================================
	=            Critical Logger Methods            =
	===============================================*/
	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	public function critical($message, array $context = array()): self
	{
		$this->log(LogLevel::CRITICAL, $message, $context);
		return $this;
	}
	/*=====  End of Critical Logger Methods  ======*/

	/*============================================
	=            Alert Logger Methods            =
	============================================*/
	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	public function alert($message, array $context = array()): self
	{
		$this->log(LogLevel::ALERT, $message, $context);
		return $this;
	}
	/*=====  End of Alert Logger Methods  ======*/

	/*================================================
	=            Emergency Logger Methods            =
	================================================*/
	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	public function emergency($message, array $context = array()): self
	{
		$this->log(LogLevel::EMERGENCY, $message, $context);
		return $this;
	}
	/*=====  End of Emergency Logger Methods  ======*/

	/*=======================================
	=            Divider Methods            =
	=======================================*/
	
	private function displayBashColor(string $bash_color = "", int $reset = 0): self
	{
		if(!empty($bash_color))
		{
			if(1 === $reset)
			{
				$bash_color = self::BASH_CLR_RESET;
			}
			
			echo $bash_color;
		}
		
		return $this;
	}

	public function logLongDivider(string $bash_color = ""): self
	{
		$this->displayBashColor($bash_color)
			->info($this->LNG_DIVIDER)
			->displayBashColor($bash_color, 1);
		
		return $this;

		// if (!empty($bash_color)) {
		// 	echo $bash_color;
		// 	$this->logInfo($this->LNG_DIVIDER);
		// 	echo self::BASH_CLR_RESET;

		// 	return $this;
		// }

		// return $this->logInfo($this->LNG_DIVIDER);
	}

	public function logMediumDivider()
	{
		return $this->logInfo($this->MED_DIVIDER);
	}

	public function logShortDivider()
	{
		return $this->logInfo($this->SML_DIVIDER);
	}

	public function logErrorDivider()
	{
		return $this->logInfo($this->ERR_DIVIDER);
	}
	/*=====  End of Divider Methods  ======*/

	/*=======================================
	=            Logging Methods            =
	=======================================*/
	private function processPreLogEntries(): self
	{
		$_pre_log_msgs  = self::$pre_log_msgs;
		$_pre_log_count = count($_pre_log_msgs);
		if(0 < $_pre_log_count)
		{
			foreach($_pre_log_msgs as $pl_key => $pl_value)
			{				
				$log_type  = key($pl_value);
				$log_entry = $pl_value[$log_type];
				$this->log($log_type, $log_entry);
			}
		}

		return $this;
	}

	// private function writeLogHeaderFooter(string $prefix = ""): self
	private function logHeaderFooter(string $prefix = ""): self
	{
        if (!empty($this->use_header_footer)) {
			
        
            // echo "\033[1;32m";

            // $_log_msg = "++--- {$prefix} "
            //         . "{$this->log_title}: "
			// 		. date("m-d-Y H:i:s");
					
			$_log_msg = "++---{$prefix} {$this->log_title}";

            $this->logLongDivider()
            	->logInfo($_log_msg)
            	->logLongDivider();

            // echo "\033[0m";
        }

		return $this;
	}

	public function logLabeledDivider(string $label): self
	{
		// $default_divider = "++----------++";
		// $label_length = strlen($label);
		// $divider_length = 60 - $label_length; // 25 = 60 - 35
		// if(0 < $divider_length)
		// {
		// 	$pre_divider  = intdiv($divider_length, 2);
		// 	$post_divider = $pre_divider;
		// 	if(0 !== $divider_length % 2)
		// 	{
		// 		$post_divider = $post_divider + 1;
		// 	}
		// }

		// Approx: 60 total
		// ++------------------++ Dividing This ++-------------------++
		// This sentence is 25 chars
		// ++------------++ This sentence is 25 chars ++-------------++
		// This sentence is 30 characters
		// ++----------++ This sentence is 30 characters ++----------++
		// This sentence is 35 characters long
		// ++-------++ This sentence is 35 characters long ++--------++
		$_label_divider = "++----------++ "
						. $label
						. " ++----------++";

		$this->info($_label_divider);
		return $this;
	}


	public function log($level, $message, array $context = array()): self
	{
		$level = strtoupper($level);
		try {
			constant("\Psr\Log\LogLevel::{$level}");
		} catch (\Exception $e) {
			$msg = "'{$level}' is not a valid Log Level. "
				. "See the PSR-3 Log Level specification.";
			throw new \Psr\Log\InvalidArgumentException($msg);
		}
				
		$message = $this->objToStr($message);
		if(!empty($context))
		{
			$message = $this->injectContext($message, $context);
		}

		$msg_prefix = "";
		if("INFO" !== $level)
		{
			$level = str_pad($level, 9, "*", STR_PAD_BOTH);
			$msg_prefix = "[{$level}]";
		}

		if(false === strpos($message, "++---"))
		{
			$message = "> " . $message;
		}
		
		$message = "{$msg_prefix}{$message}";
		$this->writeLogFile($message);

		return $this;
	}

	/**
	 * Writes the message to the Log File.
	 *
	 * @param string $_msg
	 * @param bool 	 $to_console
	 * @param string $log_type
	 *
	 * @return self
	 */
	public function writeLogFile(string $_msg, 
								bool 	$to_console = false, 
								string 	$log_type 	= "S"): self
	{
		if("E" === $log_type)
		{
			$this->error($_msg);
		}
		else
		{
			$msg_dts = date("[H:i:s]");
			if(true === $this->use_full_dts)
			{
				$msg_dts = date("[Y-m-d H:i:s]");
			}
			
            $_log_msg = $msg_dts . $_msg;
            fwrite($this->_log_handle, $_log_msg . PHP_EOL);

            $this->writeToConsole($_log_msg, $to_console);
        }

		return $this;
	}

	/**
	 * Writes the log message to the console or stdout
	 *
	 * @param string $_msg
	 * @param bool $to_console
	 *
	 * @return self
	 */
	private function writeToConsole(string $_msg, bool $to_console = false): self
	{
		if(true === $to_console || true === $this->chatterbox) 
		{
			echo $_msg . PHP_EOL;
		}

		return $this;
	}

	/*
	$_error_in = array();
	$_error_in['msg']   = "RETS Search Failed..";
	$_error_in['url'] = "http://Sorcerer.com";
	$_error_in['type'] = "rets";
	$_error_in['code'] = "20202";
	$_error_in['text'] = "Invalid select on field [Select] Select Field [L_Keyword7] does not exist in the Class [LD_2].";

	$_errors = array();
	$_errors['type']      = "RETS Errors";
	$_errors['msg']       = "++ !ERROR! {$_error_info['msg']}<br>"
							. "++ ERROR Type: {$_error_info['type']}<br>"
							. "++ ERROR Code: {$_error_info['code']}<br>"
							. "++ ERROR Msg:  {$_error_info['text']}<br>"
							. "++ RETS URL:   {$_error_info['query']}";
	 */
	public function writeError(array $_error_in, $to_console = false): self
	{
		echo "+ writeError method needs update!!!\n";
		print_r($_error_in);
		echo "\n\n";
		return $this;


		if(!empty($_error_in['threshold'])) { return $this; }

		$this->writeLogFile('+', $to_console)
			->writeLogFile($this->LNG_DIVIDER, $to_console)
			->writeLogFile($this->ERR_DIVIDER, $to_console)
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
			->writeLogFile($this->ERR_DIVIDER, $to_console)
			->writeLogFile($this->LNG_DIVIDER, $to_console)
			->writeLogFile('+', $to_console);

		return $this;
	}
	/*-----  End of Logging Methods  ------*/

	/*======================================
	=            Helper Methods            =
	======================================*/

	private function logException($_exception): self
	{
		$this->logMediumDivider()
			->writeLogFile("+ Exception Code: {$_exception->getCode()}")
			->writeLogFile("+ Exception Msg: {$_exception->getMessage()}")
			->logLabeledDivider("Exception Trace")
			->writeLogFile(PHP_EOL . $_exception->getTraceAsString())
			->logMediumDivider();

		return $this;
	}
	
	private function objToStr($message): string
	{
		if (is_object($message)) {
			if (false === method_exists($message, "__toString")) {
				$e_msg = "Object must have '__toString' implemented.";
				throw new LogWriterException($e_msg);
			}

			$message = (string) $message;
		}

		return $message;
	}

	/**
	 * Interpolates context values into the message placeholders.
	 */
	private function injectContext(string $message, array $context = array()): string
	{
		// build a replacement array with braces around the context keys
		$replace = array();
		foreach ($context as $key => $val) {
			// Handle getting Exception Code, Message, and Stack Trace from the Exception
			if("exception" === $key && is_a($val, "Exception"))
			{
				$this->logException($val);
			}
			// check that the value can be casted to string
			else if (
				!is_array($val)
				&& (!is_object($val) || method_exists($val, '__toString'))
			) {
				$replace['{' . $key . '}'] = (string) $val;
			}
		}

		// interpolate replacement values into the message and return
		return strtr($message, $replace);
	}
	/*-----  End of Helper Methods  ------*/

	/*=========================================
	=            Log File Clean-Up            =
	=========================================*/
	
	/**
	 * Runs the Automatic File Clean-up
	 * 
	 * * NOTE: Only processes the File Clean-up, 
	 * 		* IF the # of retention days are more than 0 (zero) 
	 * 		* and the 'automatic_cleanup' is TRUE.
	 *
	 * @return self
	 */
	public function logFileCleanup(): self
	{
		$this->logLongDivider()
			->logLabeledDivider("Log File Cleanup");
		
		if(0 < $this->retention && true === $this->automatic_cleanup)
		{
			$this->info("+ Log File Cleanup > ON")
				->info("+ Retention Days --> {$this->retention}")
				->info("+ Retention Date --> {$this->retention_date}")
				->info("+ Retention Unix --> {$this->retention_unix}");

			$this->buildDeleteList();
		}
		else
		{
			$this->info("+ Log File Cleanup > OFF")
				->info("+ Retention Days --> OFF");
		}

		$this->logLongDivider();

		return $this;
	}

	/**
	 * Handles checking the Log directory for any log files that need to be deleted.
	 * 
	 * Attempts to delete the log file, IF it is older than or equal to the 
	 * 	retention date.
	 *
	 * @return self
	 */
	private function buildDeleteList(): self
	{
		$del_start_time = Utilities::getMicroTimeStamp();
		$total_delete_size = 0;
		$log_dir           = dirname($this->log_filename);
		$clean_out_pieces  = array($this->log_basename, ".log.txt", ".log");

		$this->logShortDivider()
			->info("+ LOG DIRECTORY ---> " . $log_dir)
			->info("+ LOG File Base ---> " . $this->log_basename);

		$dir_files  = scandir($log_dir);
	    if($dir_files[0] == '.')    { unset($dir_files[0]); }
	    if($dir_files[1] == '..')   { unset($dir_files[1]); }

	    $file_count = count($dir_files);
	    $this->info("+ LOG DIR Count ---> " . $file_count)
			->logMediumDivider();

	    if(0 < $file_count)
	    {
	    	foreach($dir_files as $f_key => $file)
	    	{
	    		if(false !== stripos($file, $this->log_basename))
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

	    			$this->info($msg);
	    		}
	    	}
	    }

	    if(0 < (int) $this->retention_del_count)
	    {
	    	$this->logMediumDivider()
	    		->info('+ [DEL] # of Files > ' . $this->retention_del_count)
				->info('+ [DEL] Total Size > ' 
						. $this->formatFileSize($total_delete_size))
	        	->logMediumDivider();
	    }
	    else
	    {
	    	$this->logMediumDivider()
	    		->info('+ NO Log Files to Delete');
		}

		$del_end_time = Utilities::getMicroTimeStamp();
		$total_time   = $this->formatTimeStamp($del_end_time - $del_start_time);
		$this->info("+ Processing Time -> {$total_time}");

	    return $this;
	}

	// * Direct interface with the 'formatBytes' method in the Utilities class.
	private function formatFileSize($bytes): string
	{
	   	return Utilities::formatBytes($bytes);
	}

	private function convertLogDate(string $date_string): string
	{
	    if(false !== strpos($date_string, "-"))
	    {	$date_string = str_replace("-", "", $date_string);		}

	    $file_month = substr($date_string, 0, 2);
	    $file_day   = substr($date_string, 2, 2);
	    $file_year  = substr($date_string, 4);

	    return strtotime($file_year . $file_month . $file_day);
	}

	// * Direct interface with the 'getFormattedTimeStamp' method in the Utilities class.
	public function formatTimeStamp($seconds_input): string
	{
		return Utilities::getFormattedTimeStamp($seconds_input);
	}
	/*=====  End of Log File Clean-Up  ======*/

	/**
	 * [__destruct description]
	 */
	function __destruct()
	{
		if(is_resource($this->_log_handle)) { $this->closeLogFile(); }
	}
}
/* End of file LogWriter.php */
/* Location: /Sorcerer/Logging/LogWriter.php */