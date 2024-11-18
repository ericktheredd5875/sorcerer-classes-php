<?php
namespace Sorcerer\Pdo;

class PdoDatabaseException extends \Exception
{
    private $current_connection;
    private $current_query;
    private $current_bindings = array();

    private $sql_state;
    private $sql_msg;

    /*public function __construct($message, $code = 0, Exception $previous = null)
    {
        echo "+ Hello there " . __CLASS__ . "<br>";
        echo "+ Code: {$code}<br>";
        $this->processQueryException();

        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);


    }*/

    public function setCurrentConnection($name)
    {
        $this->current_connection = $name;
        return $this;
    }

    public function setCurrentQuery($data)
    {
        $this->current_query = $data;

        return $this;
    }

    public function setCurrentBindings($data)
    {
        $this->current_bindings = $data;
        return $this;
    }

    private function setSqlState()
    {
        $_code = $this->getCode();
        if(empty($_code))
        {
            $_temp_error      = explode(": ", $this->getMessage());
            $this->sql_state = $_temp_error[0];

            $_tmp_error_msg = str_replace($_temp_error[0], "", $this->getMessage());
            $this->sql_msg    = $_tmp_error_msg;
        }
        else
        {
            $this->sql_state = $this->getCode();
            $this->sql_msg    = $this->getMessage();
        }

        return $this;
    }

    public function processQueryException($calledMethod)
    {
        $this->setSqlState();
        $this->handleParamMismatch($calledMethod);

        return $this;
    }

    private function handleParamMismatch($calledMethod)
    {
        //$msg = "DB|<pre>";
        $msg = "DB|";
        $msg .= "<<---------------------------- ::ERROR:: ---------------------------->>\n";
        $msg .= "++ <b>LINE ---------></b> " . $this->getLine() . "\n";
        $msg .= "++ <b>Class/Method -></b> {$calledMethod}\n";
        $msg .= "++ <b>DB Connection ></b> {$this->current_connection}\n";
        $msg .= "++ <b>DB CODE ------></b> {$this->sql_state}\n";
        $msg .= "++ <b>DB MSG -------></b> {$this->sql_msg}\n";

        $msg .= "++ ----------------- <b>QUERY</b> ----------------- ++\n";

        $binder_count = substr_count($this->current_query, "= :");
        $msg .= "++ <b>Binding Count ></b> {$binder_count}\n";
        $msg .= "++ <b> -------------></b> {$this->current_query}\n";

        //$sub_query = substr($this->current_query,
        //        strpos($this->current_query, "= "),
        //        strlen(":site_id"));

        $cur_var_binding    = $this->current_bindings;
        $msg .= "++ ---------------- <b>BINDINGS</b> --------------- ++\n";
        $msg .= "++ <b>Binding Count ></b> " . count($cur_var_binding) . "\n";

        $i = 1;
        foreach($cur_var_binding AS $key => $data)
        {
            $fail_msg = "";
            if(false === strpos($this->current_query, $key))
            {
                $fail_msg = "<font color='#cc0000'><b>[NOT DEFINED]</b>";
            }
            else
            {
                $fail_msg = "<font color='green'><b>[DEFINED]</b>";
            }

            $msg .= "{$fail_msg}++[{$i}]++ <b>Param --></b> {$key} :=: "
                    . "<b>Value --></b> {$data['value']}"
                    . "</font>\n";

            $i++;
        }

        $msg .= "++ -------------- <b>STACK TRACE</b> -------------- ++\n";
        $msg .= "{$this->getTraceAsString()}\n";
        $msg .= "<<---------------------------- ::ERROR:: "
                . "---------------------------->>\n";

        $this->message = nl2br($msg);
        //throw new Exception($msg, 1);

        return $this;
    }
}