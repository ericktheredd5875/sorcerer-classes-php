<?php

use PHPUnit\Framework\TestCase;

use Sorcerer\Logging\LogWriter, 
    Sorcerer\Logging\LogWriterException;


class LogWriterTest extends TestCase
{
    protected $ds = "";
    protected $log_directory = "";

    protected $logger = "";

    protected function setUp(): void
    {
        $this->ds = DIRECTORY_SEPARATOR;
        $this->log_directory = dirname(__DIR__, 3) 
                        . $this->ds . "_files"
                        . $this->ds . "Sorcerer"
                        . $this->ds . "Logging"
                        . $this->ds . "logs"
                        . $this->ds;

        $this->logger = LogWriter::getInstance();
        $this->logger = LogWriter::reset();
    }

    public function testTrueAssertsToTrue()
    {
        $this->assertTrue(true);
    }

    public function testLogWriterSingleton()
    {
        $this->assertInstanceOf("Sorcerer\Logging\LogWriter", $this->logger);
    }

    public function testThatWeCanGetTheClassName()
    {
        $class_name = LogWriter::getClassName();
        $this->assertEquals("Sorcerer\Logging\LogWriter", $class_name);
    }

    public function testThatWeCanGetTheErrDivider()
    {
        $err_divider = "++-------------------------------!!! ERROR !!!" 
                    . "--------------------------------++";
        
        $logger = $this->logger;
        $this->assertEquals($err_divider, $logger->getErrDivider());
        $this->assertStringContainsString("ERROR", $logger->getErrDivider());
    }

    public function testThatWeCanGetTheLngDivider()
    {
        $lng_divider = "++-----------------------------------" 
                . "-----------------------------------------++";
        
        $logger = $this->logger;
        $this->assertEquals($lng_divider, $logger->getLngDivider());
    }

    public function testThatWeCanGetTheMedDivider()
    {
        $med_divider = "++--------------------------------------------------------++";

        $logger = $this->logger;
        $this->assertEquals($med_divider, $logger->getMedDivider());
    }

    public function testThatWeCanGetTheSmlDivider()
    {
        $sml_divider = "++------------------------------------++";

        $logger = $this->logger;
        $this->assertEquals($sml_divider, $logger->getSmlDivider());
    }

    public function testThatVerboseIsTrue()
    {
        $logger = $this->logger;
        $this->assertTrue($logger->getVerbose());
    }

    public function testThatVerboseIsFalse()
    {
        $logger = $this->logger;
        $logger->setVerbose(false);

        $this->assertFalse($logger->getVerbose());
    }

    public function testThatHeaderFooterIsTrue()
    {
        $logger = $this->logger;

        $this->assertTrue($logger->getUseHeaderFooter());

        $logger->setUseHeaderFooter("header");
        $this->assertTrue($logger->getUseHeaderFooter());
    }

    public function testThatHeaderFooterIsFalse()
    {
        $logger = $this->logger;
        $logger->setUseHeaderFooter("");

        $this->assertFalse($logger->getUseHeaderFooter());
    }

    public function testThatLogDateIsSet()
    {
        $logger = $this->logger;
        $this->assertEquals(date("Ymd"), $logger->getLogDate());
    }

    public function testThatBaseFileNameIsSet()
    {
        $filename = "test-log";
        $logger = $this->logger;
        $logger->setFileName($filename);

        $this->assertEquals($filename, $logger->getLogBasename());
    }

    public function testThatFilenameIsSet()
    {
        $directory = $this->log_directory;
        $filename  = $directory . "test-log";

        $logger = $this->logger;
        $logger = LogWriter::reset();
        $logger->setFileName($filename);
        
        $this->assertStringContainsString($filename, $logger->getLogFileName());
    }

    public function testThatLogDirectoryIsSet()
    {
        $directory = $this->log_directory;
        
        $filename = $directory . "test-log";
        $logger = $this->logger;
        $logger = LogWriter::reset();
        $logger->setFileName($filename);

        $this->assertEquals($directory, $logger->getLogDirectory());
    }

    public function testThatLogDirectoryThrowsExceptionOnNoDir()
    {
        $this->expectException(LogWriterException::class);

        $directory = $this->log_directory . "not-dir" . $this->ds;
        $filename  = $directory . "test-log";

        $logger = LogWriter::getInstance();
        $logger = LogWriter::reset();
        $logger->setFileName($filename);
    }

    public function testSettingTheFilenameAndDirectorySeperately()
    {
        $directory = $this->log_directory;
        
        $filename = "test-log";
        $logger = LogWriter::getInstance();
        $logger->setLogDirectory($directory)
            ->setFileName($filename);

        $this->assertStringContainsString($directory . $filename, 
                                            $logger->getLogFileName());
        $this->assertEquals($directory, $logger->getLogDirectory());
    }

    public function testSettingTheFilenameEmptyThrowsException()
    {
        $this->expectException(LogWriterException::class);

        $directory = $this->log_directory;
        
        $filename = "";
        $logger = LogWriter::getInstance();
        $logger->setLogDirectory($directory)
            ->setFileName($filename);
    }

    public function testLogTitleIsSet()
    {
        $log_title = "Logging Test for PHPUnit";
        $logger = LogWriter::getInstance();
        $logger->setLogTitle($log_title);

        $this->assertEquals($log_title, $logger->getLogTitle());
    }

    public function testLogHeaderFooter()
    {
        $reggy_message = "/\[START\] Logging Test for PHPUnit/";
        $this->expectOutputRegex($reggy_message);
        
        $log_title = "Logging Test for PHPUnit";
        $directory = $this->log_directory;
        $filename  = "test-log";

        $logger = LogWriter::getInstance();
        $logger->setLogDirectory($directory)
            ->setFileName($filename)
            ->setLogTitle($log_title)
            ->openLogFile()
            ->warning("+ I am WARNING YOU!")
            ->notice("+ You are on NOTICE")
            ->error("+ ERROR ---> ERROR")
            ->critical("+ Something Critical just Happened!")
            ->alert("+ ALERT ALERT... ALERT")
            ->emergency("+ BEDO BEDO... Call 911")
            ->closeLogFile();

        $this->assertStringContainsString(
            $directory . $filename,
            $logger->getLogFileName()
        );
        $this->assertEquals($directory, $logger->getLogDirectory());
    }

    // ** SO: 'log' method tests
    public function testLogMethodToConsole()
    {
        $msg_type = "LOG";
        $message = "+ This is from the {$msg_type} method.";
        $reggy_message = "/\+ This is from the {$msg_type} method\./";
        // $reggy_message = "/\[\*\*DEBUG\*\*\]\> {$message}/";
        $this->expectOutputRegex($reggy_message);

        $testOjbect = new \TestClass($message);
        
        $log_title = "Logging Test for {$msg_type}";
        $directory = $this->log_directory;
        $filename  = "test-log";

        $this->logger
            ->setLogDirectory($directory)
            ->setFileName($filename)
            ->setLogTitle($log_title)
            ->openLogFile()
            ->log("info", $message)
            ->closeLogFile();
    }

    public function testLogMethodToConsoleWithBadLogLevel()
    {
        $this->expectException(\Psr\Log\InvalidArgumentException::class);

        $msg_type = "LOG";
        $message = "+ This is from the {$msg_type} method.";
        $reggy_message = "/{$message}/";

        $testOjbect = new \TestClass($message);

        $log_title = "Logging Test for {$msg_type}";
        $directory = $this->log_directory;
        $filename  = "test-log";

        $this->logger->setVerbose(false)
            ->setLogDirectory($directory)
            ->setFileName($filename)
            ->setLogTitle($log_title)
            ->openLogFile()
            ->log("special", $message)
            ->closeLogFile();
    }

    public function testLogMethodToConsoleWithObject()
    {
        $msg_type = "LOG";
        $message = "+ This is from the {$msg_type} method.";
        $reggy_message = "/\+ This is from the {$msg_type} method\./";
        $this->expectOutputRegex($reggy_message);

        $testOjbect = new \TestClass($message);
        
        $log_title = "Logging Test for {$msg_type}";
        $directory = $this->log_directory;
        $filename  = "test-log";

        $this->logger->setLogDirectory($directory)
            ->setFileName($filename)
            ->setLogTitle($log_title)
            ->openLogFile()
            ->log("info", $testOjbect)
            ->closeLogFile();
    }

    public function testLogMethodToConsoleWithContext()
    {
        $msg_type = "LOG";
        $message = "+ This is from the {$msg_type} method.";
        $reggy_message = "/\+ This is from the {$msg_type} method\./";

        $testOjbect = new \TestClass($message);

        $log_title = "Logging Test for {$msg_type}";
        $directory = $this->log_directory;
        $filename  = "test-log";

        $this->expectOutputRegex($reggy_message);

        $context = array(
            "exception" => new LogWriterException("LogWriter Exception Here.")
        );
        $this->logger->setLogDirectory($directory)
            ->setFileName($filename)
            ->setLogTitle($log_title)
            ->openLogFile()
            ->log("info", $message, $context)
            ->closeLogFile();
    }

    // ** SO: 'info' method tests
    public function testInfoLoggerToConsole()
    {
        $log_title = "Logging Test for Info";
        $directory = $this->log_directory;
        $filename  = "test-log";

        $message = "+ This is from the Info method.";

        $reggy_message = "/\+ This is from the Info method\./";
        $this->expectOutputRegex($reggy_message);

        $this->logger->setLogDirectory($directory)
                ->setFileName($filename)
                ->setLogTitle($log_title)
                ->openLogFile()
                ->info($message)
                ->closeLogFile();
    }

    public function testInfoLoggerToConsoleWithObject()
    {
        $msg_type = "INFO";
        $message = "+ This is from the {$msg_type} method.";
        $reggy_message = "/\+ This is from the {$msg_type} method\./";

        $testOjbect = new \TestClass($message);

        $log_title = "Logging Test for {$msg_type}";
        $directory = $this->log_directory;
        $filename  = "test-log";        

        $this->expectOutputRegex($reggy_message);

        $this->logger->setLogDirectory($directory)
                ->setFileName($filename)
                ->setLogTitle($log_title)
                ->openLogFile()
                ->info($testOjbect)
                ->closeLogFile();
    }

    public function testInfoLoggerToConsoleWithContext()
    {
        $msg_type = "INFO";
        $message = "+ This is from the {$msg_type} method.";
        $reggy_message = "/\+ This is from the {$msg_type} method\./";

        $testOjbect = new \TestClass($message);

        $log_title = "Logging Test for {$msg_type}";
        $directory = $this->log_directory;
        $filename  = "test-log";

        $this->expectOutputRegex($reggy_message);

        $context = array(
                "exception" => new LogWriterException("LogWriter Exception Here.")
            );
        $this->logger->setLogDirectory($directory)
            ->setFileName($filename)
            ->setLogTitle($log_title)
            ->openLogFile()
            ->info($message, $context)
            ->closeLogFile();
    }

    // ** SO: 'debug' method tests
    public function testDebugLoggerToConsole()
    {
        $msg_type = "DEBUG";
        $message = "+ This is from the {$msg_type} method.";
        $reggy_message = "/\[\*\*DEBUG\*\*\]\> \+ This is from the {$msg_type} method\./";
        $this->expectOutputRegex($reggy_message);

        $log_title = "Logging Test for {$msg_type}";
        $directory = $this->log_directory;
        $filename  = "test-log";

        $this->logger->setLogDirectory($directory)
            ->setFileName($filename)
            ->setLogTitle($log_title)
            ->openLogFile()
            ->debug($message)
            ->closeLogFile();
    }

    public function testDebugLoggerToConsoleWithObject()
    {
        $msg_type = "DEBUG";
        $message = "+ This is from the {$msg_type} method.";
        $reggy_message = "/\[\*\*DEBUG\*\*\]\> \+ This is from the {$msg_type} method\./";

        $testOjbect = new \TestClass($message);

        $log_title = "Logging Test for {$msg_type}";
        $directory = $this->log_directory;
        $filename  = "test-log";

        $this->expectOutputRegex($reggy_message);

        $this->logger->setLogDirectory($directory)
            ->setFileName($filename)
            ->setLogTitle($log_title)
            ->openLogFile()
            ->debug($testOjbect)
            ->closeLogFile();
    }

    public function testThatPreLogEntriesAreLogged()
    {
        $msg = array();
        $msg[] = array("info"   => "I said Dart Gun not Fart Gun! -Gru");
        $msg[] = array("error"  => "You have a face... Como un burro. -Gru");
        $msg[] = array("alert"  => "It's so fluffy, I'm gonna die! -Agnes");
        LogWriter::setPreLogEntries($msg);

        
        $log_title = "Pre-Logging Test";
        $directory = $this->log_directory;
        $filename  = "test-pre-log";

        $logger = LogWriter::getInstance();
        $logger->setVerbose(false)
            ->setLogDirectory($directory)
            ->setFileName($filename)
            ->setLogTitle($log_title)
            ->openLogFile()
            ->closeLogFile();

        $this->assertEquals($directory, $logger->getLogDirectory());
    }
}