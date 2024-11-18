<?php

namespace Sorcerer\Cliff;

use SendGrid;
use Sorcerer\Cliff\ClavenException;


class Claven
{
    CONST SG_KEY = "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";

    private $debug;
    private $debug_mode;
    private $_multi_boundary;

    private $api_key;
    private $sent_code;
    private $sent_msg;
    private $sent_headers;
    private $message_id;

    private $sg_categories     = array();
    private $sg_uniques        = array();

    private $from;
    private $reply_to;

    private $bad_domains       = array();
    private $header_collection = array();
    private $_to_collection    = array();
    private $_cc_collection    = array();
    private $_bcc_collection   = array();

    private $subject;
    private $text_message;
    private $html_message;


    function __construct()
    {
        /*==========================================
        =            Set Default Params            =
        ==========================================*/
        $this->setApiKey(self::SG_KEY)
            ->setDebug(false)
            ->setDebugMode()
            ->setMultiBoundary()
            ->setBadDomains(array("localhost", ""));
        /*=====  End of Set Default Params  ======*/

        return $this;
    }

    /*======================================
    =            Setter Methods            =
    ======================================*/
    private function setMultiBoundary()
    {
        $this->_multi_boundary = "==MULTIPART_BOUNDARY_"
                                . md5(date("r", time()));

        return $this;
    }

    public function setApiKey($key)
    {
        $this->api_key = $key;
        return $this;
    }

    public function setDebug($debug)
    {
        $this->debug = (bool) $debug;

        return $this;
    }

    // Options are: cli or html
    public function setDebugMode($mode = "html")
    {
        $this->debug_mode = $mode;
        return $this;
    }

    public function setBadDomains(array $domains)
    {
        $this->bad_domains = array_merge($this->bad_domains, $domains);
        return $this;
    }

    public function setFrom($email)
    {
        $this->from = $email;

        return $this;
    }

    public function setReplyTo($email)
    {
        $this->reply_to = $email;
        $this->setMailHeader("Return-Path", $this->reply_to);

        return $this;
    }

    public function setMailHeader($key, $value)
    {
        $this->header_collection[$key] = $value;
        return $this;
    }

    public function setSendAddress($emails, $email_type = "to")
    {
        $emails = explode(";", str_replace(" ", "", $emails));
        $emails = array_filter($emails, array($this, "filterDomains"));
        sort($emails);

        $collection = array();
        $email_count = count($emails);
        if($email_count > 0)
        {
            foreach($emails AS $e_key => $email)
            {   $collection[$email] = $email;       }
        }

        switch($email_type)
        {
            case "to":  $this->_to_collection = $collection; break;
            case "cc":  $this->_cc_collection = $collection; break;
            case "bcc": $this->_bcc_collection = $collection; break;
        }

        return $this;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
        $this->setUniqueArgs("Eml-Sbjct", $this->subject);

        return $this;
    }

    public function setMsgText($text)
    {
        $this->text_message = $text;
        return $this;
    }

    public function setMsgHtml($html)
    {
        $this->html_message = $html;
        return $this;
    }

    public function setCategory($category)
    {
        if(is_array($category))
        {
            $this->sg_categories = array_merge($this->sg_categories, $category);
        }
        else if(!empty($category))
        {
            $this->sg_categories[] = $category;
        }

        return $this;
    }

    public function setUniqueArgs($key, $value)
    {
        $this->sg_uniques[$key] = $value;

        return $this;
    }

    private function setSentCode($code)
    {
        $this->sent_code = $code;
        return $this;
    }

    private function setSentMsg($msg, $decode = false)
    {
        $this->sent_msg = $msg;
        // if(true === (bool) $decode)
        // {
        //     $_msg = json_decode($msg);
        //     $this->sent_msg = $_msg->message;
        // }

        return $this;
    }

    private function setSentHeaders($headers)
    {
        // EXAMPLE> X-Message-Id: 52oAm5yFRn-kAXG9RSKkrg
        $message_id_tag = "X-Message-Id: ";

        $this->sent_headers = $headers;
        $header_count = count($this->sent_headers);
        if(!empty($header_count))
        {
            $this->message_id = "";
            foreach($headers as $h_key => $h_value)
            {
                $h_value = trim($h_value);
                if(false !== stripos($h_value, $message_id_tag))
                {
                    $message_id = str_ireplace($message_id_tag, "", $h_value);
                    $this->message_id = $message_id;
                    break;
                }
            }
        }

        return $this;
    }
    /*=====  End of Setter Methods  ======*/

    /*======================================
    =            Getter Methods            =
    ======================================*/
    public function getSentCode()
    {
        return $this->sent_code;
    }

    public function getSentMsg()
    {
        return $this->sent_msg;
    }

    public function getSentHeaders()
    {
        return $this->sent_headers;
    }

    public function getMessageId()
    {
        return $this->message_id;
    }
    /*=====  End of Getter Methods  ======*/

    /*====================================
    =            Core Methods            =
    ====================================*/
    public function sendEmail()
    {
        if(empty($this->from))
        {
            throw new ClavenException("Please specify a From address.", 1);
        }

        if(empty($this->reply_to))
        {
            $this->setReplyTo($this->from);
        }

        if(empty($this->_to_collection) || 0 === count($this->_to_collection))
        {
            throw new ClavenException("Please specify a To address.", 1);
        }

        $message_id = time() . "-" . $this->from;
        $this->setMailHeader("Message-ID", "<{$message_id}>")
            ->setMailHeader("MIME-Version", "1.0");

        $this->printEmail()
            ->sendSgEmail()
            ->resetEmail();

        return $this;
    }

    private function sendSgEmail()
    {
        if(true === $this->debug)
        {
            return $this;
        }

        $personalization = new SendGrid\Personalization();
        $personalization->setSubject($this->subject);

        $to_count = count($this->_to_collection);
        if(1 === $to_count)
        {
            $_to_email = array_shift($this->_to_collection);
            $personalization->addTo(new SendGrid\Email(null, $_to_email));
        }
        else if(1 < $to_count)
        {   
            foreach($this->_to_collection as $to_key => $to_value)
            {   $personalization->addTo(new SendGrid\Email(null, $to_value));    }
        }

        if(0 < count($this->_cc_collection))
        {   
            foreach($this->_cc_collection as $cc_key => $cc_value)
            {   $personalization->addCc(new SendGrid\Email(null, $cc_value));    }   
        }

        if(0 < count($this->_bcc_collection))
        {   
            foreach($this->_bcc_collection as $bcc_key => $bcc_value)
            {   $personalization->addBcc(new SendGrid\Email(null, $bcc_value));    }     
        }

        if(0 < count($this->sg_uniques))
        {   
            foreach($this->sg_uniques as $su_key => $su_value)
            {   $personalization->addCustomArg($su_key, $su_value);     }
        }

        $mail = new SendGrid\Mail();
        $mail->addPersonalization($personalization);
        $mail->setFrom(new SendGrid\Email(null, $this->from));
        $mail->setReplyTo(new SendGrid\ReplyTo($this->reply_to));

        if(!empty($this->text_message))
        {
            $mail->addContent(new SendGrid\Content("text/plain", $this->text_message));
        }
        
        if(!empty($this->html_message))
        {
            $mail->addContent(new SendGrid\Content("text/html", $this->html_message));
        }
        
        if(0 < count($this->sg_categories))
        {   
            foreach($this->sg_categories as $scat_key => $scat_value)
            {   $mail->addCategory($scat_value);            }
        }

        $sg = new SendGrid($this->api_key);
        $response = $sg->client->mail()->send()->post($mail);
        
        // echo "<pre>";
        // echo "+ SG:Code --> " . $response->statusCode() . "<br>";
        // echo "+ SG:Body --> <br>";
        // print_r($response->body());
        // echo "+ SG:Header > <br>";
        // print_r($response->headers());
        // print_r(apache_request_headers());
        // echo "</pre>";

        $this->setSentCode($response->statusCode())
            ->setSentMsg($response->body())
            ->setSentHeaders($response->headers());

        return $this;
    }

    private function printEmail()
    {
        if(true === $this->debug)
        {
            $header_count = count($this->header_collection);
            if(0 < $header_count)
            {
                $display = "\n\n::----- EMAIL DEBUG -----::\n";
                $display .= "-- Header:\n\n";

                $display .= "+ To: " . implode(", ", $this->_to_collection) . "\n";
                $display .= "+ CC: " . implode(", ", $this->_cc_collection) . "\n";
                $display .= "+ BCC: " . implode(", ", $this->_bcc_collection) . "\n";

                $display .= "+ From: {$this->from}\n";
                $display .= "+ ReplyTo: {$this->reply_to}\n";
                $display .= "+ Subject: {$this->subject}\n";

                foreach($this->header_collection AS $key => $value)
                {   $display .= "{$key}: {$value}\n";       }

                $display .= "\n\n-- SG Categories:\n";
                $display .= "+ " . implode(", ", $this->sg_categories) . "\n";

                if(0 < count($this->sg_uniques))
                {
                    $display .= "\n\n-- SG Unique Args:\n";
                    foreach($this->sg_uniques AS $key => $value)
                    {   $display .= "+ {$key}: {$value}\n";       }
                }

                $display .= "\n\n-- Body:\n";
                if(!empty($this->text_message) && !empty($this->html_message))
                {
                    $display .= "Content-Type: multipart/alternative; boundary={$this->_multi_boundary}\n";
                    $display .= "\n\n--{$this->_multi_boundary}\n";
                    $display .= "Content-type: text/plain; charset=ISO-8859-1\n";
                    $display .= "Content-Transfer-Encoding: 7bit\n\n";
                    $display .= "{$this->text_message}";
                    $display .= "\n\n--{$this->_multi_boundary}\n";
                    $display .= "Content-Type:text/html; charset=ISO-8859-1\n";
                    $display .= "Content-Transfer-Encoding: 7bit\n\n";
                    $display .= "{$this->html_message}";
                    $display .= "\n\n--{$this->_multi_boundary}--\n\n";
                }
                else if(!empty($this->text_message))
                {
                    $display .= "Content-type: text/plain; charset=ISO-8859-1\n";
                    $display .= "Content-Transfer-Encoding: 7bit\n\n";
                    $display .= "{$this->text_message}\n\n";
                }
                else if(!empty($this->html_message))
                {
                    $display .= "Content-Type:text/html; charset=ISO-8859-1\n";
                    $display .= "Content-Transfer-Encoding: 7bit\n\n";
                    $display .= "{$this->html_message}\n\n";
                }

                $display .= "::----- EMAIL DEBUG -----::\n\n";
            }
            else
            {
                $display = "There was nothing in the Header Collection!" . "\n\n";
            }

            if("html" === $this->debug_mode)
            {   $display = nl2br($display);     }

            echo $display;
        }

        return $this;
    }

    // Filter out Junk Domains
    private function filterDomains($email)
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if(false === $email) { return false; }

        $dom_arr = explode("@", $email);
        if(1 < count($dom_arr) && !empty($dom_arr[1]))
        {
            $email_domain = array_pop($dom_arr);
            if(in_array($email_domain, $this->bad_domains)
                || !strpos($email, "@"))
            {   return false;   }
            else
            {   return true;    }
        }
        else
        {   return false;   }
    }

    private function resetEmail()
    {
        $this->from              = "";
        $this->reply_to          = "";

        $this->header_collection = array();

        $this->_to_collection    = array();
        $this->_cc_collection    = array();
        $this->_bcc_collection   = array();

        $this->subject           = "";
        $this->text_message      = "";
        $this->html_message      = "";

        $this->sg_categories     = array();
        $this->sg_uniques        = array();

        $this->setMultiBoundary();

        return $this;
    }
    /*=====  End of Core Methods  ======*/


    function __destruct()
    {

    }
}

