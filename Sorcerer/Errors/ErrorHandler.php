<?php
namespace Sorcerer\Errors;
require_once __DIR__ . "/ErrorHandlerConfig.php";

use Sorcerer\Cliff\Claven;
use Sorcerer\Logging\LogWriter;
use Sorcerer\Pdo\PdoDatabase;


class ErrorHandler
{
    CONST ERROR_HTML = "https://{yourwebsite}/error.html";
    CONST ERROR_FILENAME = "error-";

    private $handler_mode;

    private $logging_type;
    private $error_constants = array();

    private $log_to_file = false;
    private $log_file;
    private $logger;

    private $log_to_db = false;
    private $_db;
    private $_site_db_conn;
    //private $_idx_db_conn;

    private $log_to_email = false;
    private $email_to;
    private $mail_queue = array();
    private $mailer;

    private $log_to_display = false;
    private $error_page_displayed = false;

    // $this->site
    private $site = "";
    private $idx = "";

    private $error_time;
    private $error_level;
    private $error_number;
    private $error_string;
    private $error_file;
    private $error_filename;
    private $error_line;
    private $error_url;
    private $error_cookie;
    private $error_method;
    private $error_agent;

    private $display_msg = array();
    private $trace_stack;

    public function __construct($mode = "DEV")
    {
        $this->setMode($mode);

        $this->error_constants = array(
                1     => "Error",
                2     => "Warning",
                4     => "Parsing Error",
                8     => "Notice",
                16    => "Core Error",
                32    => "Core Warning",
                64    => "Compile Error",
                128   => "Compile Warning",
                256   => "User Error",
                512   => "User Warning",
                1024  => "User Notice",
                2048  => "PHP Strict",
                4096  => "Recoverable Error",
                8192  => "Future Deprecated",
                16384 => "Future Deprecated",
            );

        return $this;
    }

    public function setIDX($idx)
    {
        $this->idx = $idx;
        return $this;
    }

    public function setSite($site)
    {
        $this->site = $site;
        return $this;
    }

    public function setMode($mode)
    {
        $mode = strtoupper($mode);
        if("DEV" === $mode
            || "TEST" === $mode
            || "LIVE" === $mode
            || "CLI"  === $mode)
        {
            $this->handler_mode = $mode;
        }
        else
        {
            $msg = "Error mode can only be 'DEV', 'TEST', 'LIVE', or 'CLI'.";
            trigger_error($msg, E_ERROR);
        }

        return $this;
    }

    public function setLogging($logging_type,
                                $logging_item = "",
                                $logging_resource = "")
    {
        $this->logging_type = $logging_type;

        if("file" === $logging_type)
        {
            if($logging_resource instanceof LogWriter)
            {
                if(empty($logging_item))
                {   $logging_item = $_SERVER["DOCUMENT_ROOT"] . "/";    }

                $this->log_to_file = true;
                $this->log_file    = $logging_item;
                $this->logger      = $logging_resource;
            }
            else
            {
                $msg = "'file' logging type requires '\$logging_resource' to be "
                    . "a LogWriter instance.";
                trigger_error($msg, E_USER_ERROR);
            }
        }
        else if("db" === $logging_type)
        {
            if($logging_resource instanceof PdoDatabase)
            {
                $this->log_to_db = true;
                $this->_db       = $logging_resource;
            }
            else
            {
                $msg = "'db' logging type requires '\$logging_resource' to be "
                    . "a PdoDatabase instance.";
                trigger_error($msg, E_USER_ERROR);
            }
        }
        else if("mail" === $logging_type)
        {
            if($logging_resource instanceof Claven)
            {
                $this->log_to_email = true;
                $this->email_to     = $logging_item;
                $this->mailer       = $logging_resource;
            }
            else
            {
                $msg = "'mail' logging type requires '\$logging_resource' to be "
                    . "a Claven instance.";
                trigger_error($msg, E_USER_ERROR);
            }
        }
        else if("display" === $logging_type)
        {
            $this->log_to_display = true;
        }

        return $this;
    }

    public function setErrorHandler()
    {
        if("LIVE" === $this->handler_mode)
        {
            error_reporting(E_ALL & ~E_NOTICE);
            set_error_handler(array($this, "dealWithIt"));
            set_exception_handler(array($this, "dealWithItException"));
        }
        else if("TEST" === $this->handler_mode
            || "CLI" === $this->handler_mode)
        {
            error_reporting(E_ALL & ~E_NOTICE);
            set_error_handler(array($this, "dealWithIt"));
            set_exception_handler(array($this, "dealWithItException"));
        }
        else if("DEV" === $this->handler_mode)
        {
            error_reporting(-1);
            set_error_handler(array($this, "dealWithIt"));
            set_exception_handler(array($this, "dealWithItException"));
        }

        return $this;
    }

    private function getErrorLevel($errno)
	{
        if(!empty($this->error_constants[$errno]))
        {   return $this->error_constants[$errno];      }

        return $errno;
    }

    private function getServerVar($server_var)
    {
        $server_var_value = $server_var . " is EMPTY";
        if(!empty($_SERVER[$server_var]))
        {   $server_var_value = $_SERVER[$server_var];    }

        return $server_var_value;
    }

    public function dealWithItException($exception)
    {
        /*echo "<br>Exception:<br>" . $exception->getTraceAsString();
        echo "<br>Message: {$exception->getMessage()}<br>";
        echo "<br>CODE: {$exception->getCode()}<br>";
        echo "<br>File: {$exception->getFile()}<br>";
        echo "<br>Line: {$exception->getLine()}<br>";
        echo "<br>CODE: {$exception->getCode()}<br>";*/

        $this->error_time     = date("M-d-Y H:i");
        $this->error_number   = $exception->getCode();
        $this->error_level    = $this->getErrorLevel($exception->getCode());
        $this->error_string   = $exception->getMessage();
        $this->error_file     = $exception->getFile();
        $this->error_filename = basename($exception->getFile());
        $this->error_line     = $exception->getLine();

        $this->error_url      = $this->getServerVar("REQUEST_URI");
        $this->error_cookie   = $this->getServerVar("HTTP_COOKIE");
        $this->error_method   = $this->getServerVar("REQUEST_METHOD");
        $this->error_agent    = $this->getServerVar("HTTP_USER_AGENT");

        $this->trace_stack    = nl2br($exception->getTraceAsString());

        $this->formatErrorMsg()->processLogging();

        return true;
    }

    public function dealWithIt($errno, $errstr, $errfile, $errline)
    {
  		$replevel = error_reporting();

		if(($errno & $replevel) == $errno)
		{
			$NEW_LINE_FEED = "<br>" . PHP_EOL;

            $this->error_time     = date("M-d-Y H:i");
            $this->error_number   = $errno;
            $this->error_level    = $this->getErrorLevel($errno);
            $this->error_string   = $errstr;
            $this->error_file     = $errfile;
            $this->error_filename = basename($errfile);
            $this->error_line     = $errline;

            $this->error_url      = $this->getServerVar("REQUEST_URI");
            $this->error_cookie   = $this->getServerVar("HTTP_COOKIE");
            $this->error_method   = $this->getServerVar("REQUEST_METHOD");
            $this->error_agent    = $this->getServerVar("HTTP_USER_AGENT");

            $e = new \Exception;
            $this->trace_stack = nl2br($e->getTraceAsString());

            $this->formatErrorMsg()->processLogging();
		}

        return true;
    }

    private function processLogging()
    {
        $this->logToDisplay()
            ->logToDb()
            ->logToFile()
            ->addToEmailQueue()
            ->displaySiteErrorPage();

        return $this;
    }

    /*===============================================
    =            Display Logging Methods            =
    ===============================================*/
    private function logToDisplay()
    {
        if(true === $this->log_to_display
            && ("DEV" === $this->handler_mode
                || "TEST" === $this->handler_mode))
        {
            echo "<div style='font-family:verdana;font-size:14px;'>"
                . implode("<br>", $this->display_msg)
                . "</div><br>";
        }
        else if(true === $this->log_to_display
            && "CLI" === $this->handler_mode)
        {
            $display = implode(PHP_EOL, $this->display_msg);
            $display = str_replace(array("<b>", "</b>"), "", $display);
            echo $display . PHP_EOL . PHP_EOL;
        }

        return $this;
    }

    private function displaySiteErrorPage()
    {
        if("LIVE" === $this->handler_mode
            && false === $this->error_page_displayed)
        {
            $err_msg = file_get_contents(self::ERROR_HTML);
            echo $err_msg;

            $this->error_page_displayed = true;
        }

        return $this;
    }
    /*=====  End of Display Logging Methods  ======*/

    /*=============================================
    =            Email Logging Methods            =
    =============================================*/
    function addToEmailQueue()
    {
        if(true === $this->log_to_email
            && "USER_NOTICE" !== $this->error_level)
        {
            $this->mail_queue[] = "<div style='font-family:Arial;font-size:14px;'>"
                                . implode("<br>", $this->display_msg)
                                . "</div><br>";

            // Using the Class' __destruct method instead of
            // register_shutdown_function();
            // Should work, but leaving this bit encase it does not
            // work smoothly:
            // !! -- FYI -- !! IF this needs to be turned back on,
            // needs to be updated to be created ONLY Once!
            // The method in the __destruct will also need to be Turned Off.
            //register_shutdown_function(array(&$this,"sendErrorMsg"));
        }

        return $this;
    }

    public function sendErrorMsg()
    {
        //if("DEV" === $this->handler_mode)
        //{   echo "++ ERROR MSG PRE-INSERT into DB -----!!!\n";  }

        $mail_count = count($this->mail_queue);
        if(true === $this->log_to_email && 0 < $mail_count)
        {
            $from_address = ERR_EMAIL_FROM;
            $to_address = $this->email_to;

            if(!empty($this->idx))
            {   $site_name = $this->idx;    }
            else
            {   $site_name = $this->site;   }

            $subject = "ERROR Report from {$site_name}";

            $email_msg = "";
            foreach($this->mail_queue as $key => $msg)
            {
                $email_msg .= "<h2>ERROR #"
                            . $key + 1
                            . "</h2>{$msg}<br>";
            }

            //$claven = new ClavenSg\ClavenSg();
            $this->mailer->setCategory("Error-Report")
                ->setUniqueArgs("Site-Domain",  $this->site)
                ->setUniqueArgs("Script-Page",  $this->error_file)
                ->setFrom(ERR_EMAIL_FROM)
                ->setSendAddress($this->email_to)
                ->setSubject($subject)
                ->setMsgHtml($email_msg)
                ->sendEmail();

            /*
            //$mailer = new Claven;
            $this->mailer->use_php_mail(true);
            $this->mailer->set_from_address($from_address);
            $this->mailer->set_to_address($to_address);
            $this->mailer->set_content_type("html");
            $this->mailer->set_subject($subject);
            $this->mailer->set_message($email_msg);
            //$this->mailer->print_email();
            $this->mailer->send_mail();
            */
        }

        return true;
    }
    /*=====  End of Email Logging Methods  ======*/

    /*============================================
    =            File Logging Methods            =
    ============================================*/
    function logToFile()
    {
        $msg_count = count($this->display_msg);

        if(true === $this->log_to_file && 0 < $msg_count)
        {
            $file_location = $this->log_file . self::ERROR_FILENAME;

            $this->logger->setConsoleFlag(false)
                    ->setUseHeaderFooter(false)
                    ->setLogFileName($file_location)
                    ->openLogFile();

            foreach($this->display_msg as $key => $msg)
            {   if(!empty($msg)) { $this->logger->INFO($msg); }   }

            $this->logger->closeLogFile();
        }

        return $this;
    }
    /*=====  End of File Logging Methods  ======*/

    /*==========================================
    =            DB Logging Methods            =
    ==========================================*/
    private function logToDb()
    {
        if(true === (bool) $this->log_to_db)
        {
            $this->openDbConnection()
                ->storeInDb()
                ->restoreIdxDb();
        }

        return $this;
    }

    private function openDbConnection()
    {
        $this->_site_db_conn = $this->_db->getActiveConnection();
        if(false === $this->_db->checkForConnection("SITE-ERROR"))
        {
            $this->_db->createConnection(ERR_DB_HOST,
                                        ERR_DB_DATABASE,
                                        ERR_DB_USERNAME,
                                        ERR_DB_PASSWORD, "SITE-ERROR");
        }

        $this->_db->setActiveConnection("SITE-ERROR");

        if("DEV" === $this->handler_mode)
        {   echo "\n\n++ OPEN ERROR DB CONNECTION ---------!!!\n";  }

        return $this;
    }

    private function restoreIdxDb()
    {
        if("DEV" === $this->handler_mode)
        {   echo "++ RESTORE SITE DB CONNECTION -------!!!\n\n\n";  }

        $this->_db->setActiveConnection($this->_site_db_conn);
        return $this;
    }

    private function storeInDb()
    {
        if(!empty($this->error_number))
        {
            if(!empty($this->idx))
            {
                $field     = "idx";
                $site_name = $this->idx;
            }
            else
            {
                $field     = "site";
                $site_name = $this->site;
            }

            $_error_string = $this->error_string
                            . "\n\nStack Trace\n"
                            . $this->trace_stack;

            if("DEV" === $this->handler_mode)
            {   echo "++ ERROR MSG PRE-INSERT into DB -----!!!\n";  }

            $sql = "INSERT INTO errors
                        SET {$field} = :site_name,
                        error_level  = :error_level,
                        error_number = :error_number,
                        error_string = :error_string,
                        error_file   = :error_file,
                        error_line   = :error_line,
                        error_url    = :error_url,
                        error_cookie = :error_cookie,
                        error_method = :error_method,
                        error_agent  = :error_agent";
            $this->_db->setQuery($sql)
                    ->setBinding(":site_name",      $site_name)
                    ->setBinding(":error_level",    $this->error_level)
                    ->setBinding(":error_number",   $this->error_number)
                    ->setBinding(":error_string",   $_error_string)
                    ->setBinding(":error_file",     $this->error_file)
                    ->setBinding(":error_line",     $this->error_line)
                    ->setBinding(":error_url",      $this->error_url)
                    ->setBinding(":error_cookie",   $this->error_cookie)
                    ->setBinding(":error_method",   $this->error_method)
                    ->setBinding(":error_agent",    $this->error_agent);
            $insert_id = $this->_db->getResultSet("insert");

            if("DEV" === $this->handler_mode)
            {   echo "++ ERROR MSG INSERTED into DB -------!!! [{$insert_id}]\n";  }
        }
        else
        {
            if("DEV" === $this->handler_mode)
            {   echo "++ ERROR MSG INSERT -- SKIPPED ------!!!\n";  }
        }

        return $this;
    }
    /*=====  End of DB Logging Methods  ======*/

    /*====================================================
    =            Format Error Message Methods            =
    ====================================================*/
    private function formatErrorMsg()
    {
        $this->display_msg[] = LogWriter::getLongDivider();
        $this->display_msg[] = LogWriter::getErrorDivider();
        $this->display_msg[] = "<b>Error Timestamp--></b>  {$this->error_time}";
        $this->display_msg[] = "<b>Error Number-----></b>  {$this->error_number}";
        //$this->display_msg[] = "<b>Error Incrementor></b>  {$this->error_counter}";
        $this->display_msg[] = "<b>Application ------></b>  {$this->site}";
        $this->display_msg[] = LogWriter::getMediumDivider();

        $this->display_msg[] = "<b>DETAIL FROM SCRIPT:</b>";

        if(false !== stripos($this->error_string, "DB|"))
        {
            $this->display_msg[] = "";
            $error_string = str_replace("DB|", "", $this->error_string);
            $this->display_msg[] = $error_string;
        }
        else
        {
            $this->display_msg[] = "<b>Error Type--------></b>  {$this->error_level}";
            $this->display_msg[] = "<b>Error Msg---------></b>  {$this->error_string}";
            $this->display_msg[] = "<b>Offending Script--></b>  {$this->error_file}";
            $this->display_msg[] = "<b>Error Line--------></b>  {$this->error_line}";
        }
        $this->display_msg[] = LogWriter::getMediumDivider();

        $http_host    = $this->getServerVar("HTTP_HOST");
        $http_referer = $this->getServerVar("HTTP_REFERER");
        $query_str    = $this->getServerVar("QUERY_STRING");
        $remote_addr  = $this->getServerVar("REMOTE_ADDR");

        $this->display_msg[] = "<b>SERVER VARIABLES:</b>";
        $this->display_msg[] = "<b>HTTP Host---------></b>  {$http_host}";
        $this->display_msg[] = "<b>Referring URL-----></b>  {$http_referer}";
        $this->display_msg[] = "<b>Request URL-------></b>  {$this->error_url}";
        $this->display_msg[] = "<b>Query String------></b>  {$query_str}";
        $this->display_msg[] = "<b>Kookie Kontents --></b>  {$this->error_cookie}";
        $this->display_msg[] = "<b>Req Method--------></b>  {$this->error_method}";
        $this->display_msg[] = "<b>Client Agent------></b>  {$this->error_agent}";
        $this->display_msg[] = "<b>Client IP---------></b>  {$remote_addr}";
        $this->display_msg[] = LogWriter::getMediumDivider();
        $this->display_msg[] = LogWriter::getMediumDivider();

        $this->display_msg[] = "<b>Stack Trace:</b>";
        $this->display_msg[] = $this->trace_stack;
        $this->display_msg[] = LogWriter::getErrorDivider();
        $this->display_msg[] = LogWriter::getLongDivider();

        return $this;
    }
    /*=====  End of Format Error Message Methods  ======*/


    public function __destruct()
    {
        $this->sendErrorMsg();
    }
}