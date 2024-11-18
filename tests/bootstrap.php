<?php

require_once "./vendor/autoload.php";

class TestClass
{
    public $foo;

    public function __construct($foo)
    {
        $this->foo = $foo;
    }

    public function __toString()
    {
        return $this->foo;
    }
}

// const EMERGENCY = 'emergency';  // 9 [emergency]
// const ALERT     = 'alert';      // 5 [--alert--]
// const CRITICAL  = 'critical';   // 8 [critical-]
// const ERROR     = 'error';      // 5 [--error--]
// const WARNING   = 'warning';    // 7 [-warning-]
// const NOTICE    = 'notice';     // 6 [-notice--]
// const INFO      = 'info';       // 4 [--info---]
// const DEBUG     = 'debug';      // 5 [--debug--]