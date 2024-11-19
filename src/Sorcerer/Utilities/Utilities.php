<?php
/**
 * Utility Methods
 * 
 * A collection of commonly/constantly used utility methods.
 *
 * @package     Sorcerer\Utilities\Utilities
 * @author      Eric Harris <ericktheredd5875@gmail.com>
 * @copyright   2024 - Eric Harris
 * @version     x.x.x - Comments or Notes
 * @deprecated  x.x.x - Comments or Notes
 */

/*----------  Class Namespacing  ----------*/
namespace Sorcerer\Utilities;

/*==================================================
=            Classes Used by this Class            =
==================================================*/
use Sorcerer\Utilities\UtilitiesException;
/*=====  End of Classes Used by this Class  ======*/

class Utilities
{
    /*========================================
    =            Class Attributes            =
    ========================================*/
    private static $class_name    = "";
    private static $instance      = "";
    /*=====  End of Class Attributes  ======*/

    public function __construct()
    {
        /* Initialize the Class */
        $class_name = __CLASS__;
        if (self::$instance instanceof $class_name) {
            return self::$instance;
        }
        else
        {
            self::$instance   = $this;
            self::$class_name = $class_name;
        }

        

        return self::$instance;
    }

    /**
     * Maintains a single instance of itself.
     * 
     * @return      self    Returns an instance of the class object.
     */
    public static function getInstance(): self
    {
        $class_name = __CLASS__;
        if (!(self::$instance instanceof $class_name)) {
            self::$instance  = new $class_name;
        }

        return self::$instance;
    }

    /**
     * Provides the name of the Class
     * 
     * @return  string   Returns the name of this class.
     */
    public static function getClassName(): string
    {
        return __CLASS__;
    }

    /*===================================
    =            Set Methods            =
    ===================================*/
    
    /*=====  End of Set Methods  ======*/

    /*===================================
    =            Get Methods            =
    ===================================*/
    
    /*=====  End of Get Methods  ======*/

    /**
     * Formats a phone number
     * 
     * * NOTE: Only numeric digits are handled. So phone numbers containing alpha 
     *      characters will not be parsed and formatted properly. 
     *      IE: 1-800-CAL-LSMEE
     * 
     * * NOTE: If the phone number length === 7, then the Area Code is assumed 
     *      not to be in the phone number, so the formatted phone number 
     *      will only represent the Prefix and Number. (IE: 555-6969)
     * * NOTE: If the phone number length === 11, then the Country Code is assumed 
     *      to be part of the phone number and will be trimmed off. 
     * 
     * Formats the phone number into 4 styles:
     * - [0] None, No formatting. 
     *  - IE: 5095556969
     * - [1] Paranthesis around the area code. 
     *  - IE: (509) 555-6969
     * - [2] Hyphens between each section of the phone number.
     *  - IE: 509-555-6969 
     * - [3] Periods between each section of the phone number.
     *  - IE: 509.555.6969
     *
     * @param   mixed   $phone_number   The phone number to be formatted.
     * @param   int     $format_mode    The type of formatting to be used.
     *                                  * See the examples above for formatting.    
     *
     * @return string   If the phone number length === 7, then the Area Code is assumed 
     *                      not to be in the phone number, so the returned phone number 
     *                      will only be the Prefix and Number. (IE: 555-6969)
     *                  If the phone number length === 11, 
     *                      then the Country Code is assumed to be apart of the 
     *                      phone number and will be trimmed off. 
     */
    public static function formatPhoneNumber($phone_number, 
                                        int  $format_mode = 0): string
    {
        $formatted_phone = $phone_number;

        // Strip ALL non-digit characters from the Phone Number
        $phone_number     = preg_replace("/[^0-9]/", "", $phone_number);
        $phone_num_length = strlen($phone_number);

        // * Area Code is not represented (7 = Prefix + Number)
        if(7 === $phone_num_length)
        {
            $prefix = substr($phone_number, 0, 3);
            $number = substr($phone_number, 3, 4);

            $formatted_phone = "{$prefix}-{$number}";
        } 
        /**
         * MUST have either 10 or 11 numbers in length.
         * 
         * * Valid 10 digit phone number contains: 
         *  - Area Code, Prefix, and Number
         * 
         * * Valid 11 digit phone number contains: 
         *  - Country Code, Area Code, Prefix, and Number
         */
        else if(10 === $phone_num_length || 11 === $phone_num_length)
        { 
            // Trim OFF the Country Code
            if(11 === $phone_num_length) { $country = substr($sPhone, 1); }

            $area   = substr($phone_number, 0, 3);
            $prefix = substr($phone_number, 3, 3);
            $number = substr($phone_number, 6, 4);

            if($format_mode == 1)       // Parenthesis
            {   $formatted_phone = "({$area}) {$prefix}-{$number}";     }
            else if($format_mode == 2)  // Hyphen
            {   $formatted_phone = "{$area}-{$prefix}-{$number}";       }
            else if($format_mode == 3)  // Periods
            {   $formatted_phone = "{$area}.{$prefix}.{$number}";       }
            else                        // None
            {   $formatted_phone = "{$area}{$prefix}{$number}";         }
        }
        
        return $formatted_phone;
    }

    /**
     * Parses a Full Name into its parts.  
     * 
     * @param   string  $full_name  The name to be parsed. 
     *                                  IE: Mr. Eric L Harris Sr.
     *
     * @return  array   An array containing the parsed name.
     *                  *NOTE: Middle names/initials are left attached to the Last name. 
     *                      This is done to handle compound last names, 
     *                      such as 'von Sloot'.
     *                  *NOTE: This parser does not handle profession or honorary related 
     *                      suffixes (HRH, MD, Esq, etc.)
     *                  The 'full' array entry is all the name parts put back together, 
     *                      using the grammatical standard for names with 
     *                      prefixes (Mr., Miss., etc) and suffixes (Jr., Sr., etc.).
     *                  IE: array(
     *                          "prefix" => "Mr.",
     *                          "first"  => "Eric",
     *                          "last"   => "L Harris",
     *                          "suffix" => "Sr.",
     *                          "full"   => "Mr. Eric L Harris, Sr.",
     *                          "org"    => "Mr. Eric L Harris Sr.",
     *                      ); 
     */
    public static function parseFullName(string $full_name): array
    {
        $name_prefixes = array(
                        "mr"   => "Mr.",
                        "ms"   => "Ms.",
                        "mrs"  => "Mrs.",
                        "miss" => "Miss.",
                        "dr"   => "Dr.",
                    );
        
        $name_suffixes = array(
                        "jr"     => "Jr.",
                        "jnr"    => "Jr.",
                        "junior" => "Jr.",
                        "sr"     => "Sr.",
                        "snr"    => "Sr.",
                        "senior" => "Sr.",
                        "ii"     => "II",
                        "iii"    => "III",
                    );

        $name = array(
                    "prefix" => "",
                    "first"  => "",
                    "last"   => "",
                    "suffix" => "",
                    "full"   => "",
                    "org"    => $full_name,
                );

        // Remove extra whitespacing between characters. 
        // IE: FirstName   LastName = FirstName LastName
        $full_name          = preg_replace("/\s+/", " ", $full_name);
        $name["full"]       = $full_name;                

        $name_parts         = explode(" ", $full_name);
        $name_parts_count   = count($name_parts);
        if(1 < $name_parts_count)
        {
            // Check for a Prefix Title: Mr., Miss, etc.
            $prefix_check = $name_parts[0];
            $prefix_check = strtolower(trim($prefix_check, "\."));
            if(true === array_key_exists($prefix_check, $name_prefixes))
            {
                $name["prefix"] = $name_prefixes[$prefix_check];
                array_shift($name_parts);
                $name_parts_count--;
            }

            // Check for a Suffix Title: Jr, Sr, III, etc.
            $suffix_check = $name_parts[($name_parts_count - 1)];
            $suffix_check = strtolower(trim($suffix_check, "\."));
            if(true === \array_key_exists($suffix_check, $name_suffixes))
            {
                $name["suffix"] = $name_suffixes[$suffix_check];
                array_pop($name_parts);
                $name_parts_count--;
            }

            // Meaning there is more just First or Last Name.
            if(1 < $name_parts_count)
            {
                $name["first"] = trim(array_shift($name_parts));
                $name["last"]  = trim(implode(" ", $name_parts), "\,");
            }
            else if(1 === $name_parts_count)
            {
                $name["last"] = trim(array_shift($name_parts), "\,");
            }

            $full_name_parts = $name;
            if(!empty($full_name_parts))
            {
                unset($full_name_parts["suffix"]);
                unset($full_name_parts["full"]);
                unset($full_name_parts["org"]);

                $name["full"] = implode(" ", $full_name_parts);
                $name["full"] = preg_replace("/\s+/", " ", $name["full"]);

                if(!empty($name["suffix"]))
                {
                    // No Comma is used
                    if("II" === $name["suffix"] || "III" === $name["suffix"])
                    {   $name["full"] .= " {$name["suffix"]}";          }
                    else
                    {   $name["full"] .= ", {$name["suffix"]}";         }
                }
            }            
        }

        return $name;
    }


    /**
     * Get the Peak Memory usage of PHP for the running script.
     * 
     * @param   bool        $format True will format the result. (IE: 386k or 3M)
     *                              False will leave the result as an int. (IE: 386)
     * 
     * @return  string|int  See '$format' above for example results.
     */
    public static function getMemoryUsage(bool $format = true)
    {
        $memory_usage = memory_get_peak_usage(true);
        if(true === $format)
        {   $memory_usage = self::formatBytes($memory_usage, 2);    }

        return $memory_usage;
    }

    /**
     * Format Int or Float values into Bytes.
     * 
     * IE: 1,024 = 1KB (Kilobyte), 1,024,000 = 1MB (Megabyte) 
     *
     * @param   int|float   $size       The number to be converted and formatted.
     *                                  *NOTE: '$size' will be type casted into a Float.
     * @param   int         $precision  The number of decimal places to limit 
     *                                  the result to. 
     *                                  (IE: $precision = 2; 3.86565 = 3.87)
     *                                  *Default '$precision' = 2
     *
     * @return  string      The result of the conversion. (IE: 1M, 3G, etc.)
     */
    public static function formatBytes($size, int $precision = 2): string
    {
        $suffixes    = array("", "KB", "MB", "GB", "TB");

        // Force '$size' into being a float value.
        $size        = (float) $size;

        $base        = log($size) / log(1024);
        $byte_floor  = floor($base);
        $byte_size   = round(pow(1024, ($base - $byte_floor)), $precision);
        $byte_suffix = $suffixes[$byte_floor];
        
        return $byte_size . $byte_suffix;
    }
    
    /*============================================
    =            Unique ID Generators            =
    ============================================*/

    /**
     * Generate a Unique String.
     * 
     * *Note: This is a cryptographically strong unique string. 
     *      So it could be used as a password or salt/seed.
     * 
     * *Note: The default byte length is 10, which is a string length of 20.
     *      (IE: 39d5605d124707e437e4, c55c634e9fc3d2694c7d)
     *
     * @param   int     $byte_length    The number of 'bytes' to be generated. 
     *                                  Finished string will be twice the 'byte length'. 
     *                                  *Default '$byte_length' = 10 
     *                                      The generated string length will be 
     *                                      20 characters.
     * 
     * @return  string  A randomly generated unique string.
     */
    public static function generateUniqueString(int $byte_length = 10): string
    {
        $rand_str = openssl_random_pseudo_bytes($byte_length, $crypto_strong);
        $rand_str = bin2hex($rand_str);

        return $rand_str;
    }

    /**
     * Generate a KHash String
     * 
     * This can be used to generate a unique string or a reproducable hash. 
     * 
     * * To generate a reproducable hash, use the '$input' string along with the 
     *      'date' option for '$date_type'.
     * 
     * * NOTE: The generated string will be 8 characters in length.
     *      (IE: 36779cff, 1b58b5a3)
     * 
     * @param   string $input       A string used in generating the hash. 
     *                              If '$input' is empty, a unique string is generated.
     * @param   string $date_type   Detemines what type of date to use.
     *                              Options are: 'date' or 'time' [default]
     *                              Using the 'date' option, uses 'date("Y-m-d")'.
     *                                  Useful for when you want the hash to represent a 
     *                                  specific day.
     *                              Using the 'time' option, uses 'time()'.
     *
     * @return string               The generated hash value.
     */
    public static function generateKHash(string $input       = "", 
                                        string  $date_type   = "time"): string
    {
        if(empty($input)) { $input = self::generateUniqueString(); }

        $time_input = time();
        if("date" === $date_type) { $time_input = date("Y-m-d"); }

        $input .= ":" . $time_input;
        return str_pad(dechex(crc32($input)), 8, "0", STR_PAD_LEFT);
    }

    /**
     * Generates a UUID
     * 
     * IE: 6FAF681A-5EEF-B254-A395-0DD93E207E22
     *
     * @param   string  $input A string of characters that will be used to 
     *                          generate the UUID string.
     *
     * @return  string  The generated UUID string.
     */
    public static function generateUuid(string $input = ""): string
    {
        if(empty($input)) { $input = self::generateUniqueString(); }

        $hashstring = strtoupper(md5($input));

        $s1 = substr($hashstring, 0,  8);
        $s2 = substr($hashstring, 8,  4);
        $s3 = substr($hashstring, 12, 4);
        $s4 = substr($hashstring, 16, 4);
        $s5 = substr($hashstring, 20, 12);

        return "{$s1}-{$s2}-{$s3}-{$s4}-{$s5}";
    }
    /*=====  End of Unique ID Generators  ======*/

    /*======================================
    =            Cookie Methods            =
    ======================================*/
    /**
     * Creates a Cookie
     * 
     * *NOTE:* '$value' will be serialized, in order to compact the array into a string.
     *          The 'grabCookie' method will apply unserialize to reinstate the array.
     * 
     * @param   string  $name       Name of the Cookie.
     * @param   array   $value      The collection of information to be stored.
     * @param   int     $expire     The amount of time before the Cookie expires.
     *                              0 (int) will set a negative expiration, 
     *                                  causing the cookie to be destroyed/deleted.
     * @param   string  $path       Directory path the cookie should be associated with. 
     *                                  See the PHP documentation for more information.
     * @param   string  $domain     Domain the cookie should be association with.
     *                                  See the PHP documentation for more information. 
     * @param   bool    $do_encode  True will apply 'base64_encode' to '$value'.
     *                              False will leave '$value' as is.
     *
     * @return  bool    Returns the status of the 'setcookie' function.
     */
    // public static function createCookie(string  $name, 
    public static function setCookie(string  $name, 
                                        array   $value, 
                                        int     $expire     = 0, 
                                        string  $path       = "", 
                                        string  $domain     = "", 
                                        bool    $do_encode  = true): bool
    {
        $cookie_value = "";
        if(!empty($value))
        {
            $cookie_value = serialize($value);
            if(true === $do_encode)
            {   $cookie_value = base64_encode($cookie_value);       }
        }
        
        $cookie_path = "/";
        if(!empty($path))
        {   $cookie_path = $path;    }

        $cookie_domain = $_SERVER["HTTP_HOST"];
        if(!empty($domain))
        {   $cookie_domain = $domain;       }

        if(false !== stripos($cookie_domain, "www."))
        {   $cookie_domain = str_ireplace("www.", "", $cookie_domain);      }

        $cookie_expire = $expire;
        if(0 === $expire)
        {   $cookie_expire = strtotime("-1 hour");      }

        $status = setcookie($name, 
                            $cookie_value, 
                            $cookie_expire, 
                            $cookie_path, 
                            $cookie_domain);

        return $status;
    }

    /**
     * Gets the contents of the Cookie
     * 
     * @param   string          $name   Name of the Cookie
     * @return  boolean|array   $cookie Returns either false for 'NO Cookie' 
     *                              or the array of items inside the Cookie.
     */
    // public static function grabCookie(string $name, bool $do_decode = true)
    public static function getCookie(string $name, bool $do_decode = true)
    {
        $cookie = false;
        if(!empty($_COOKIE[$name]))
        {
            $cookie = $_COOKIE[$name];

            if(true === $do_decode) { $cookie = base64_decode($cookie); }
            
            $cookie = unserialize($cookie);
        }
        
        return $cookie;
    }
    /*=====  End of Cookie Methods  ======*/


    /*===========================================
    =            TimeStamp Functions            =
    ===========================================*/
    /**
     * Gets the current Unix timestamp with microseconds.
     *
     * This can be used in conjunction with the 'getFormattedNumber' 
     *  and 'getFormattedTimeStamp' methods for generating an elapsed time display.
     * 
     * *Usage Examples:
     *  - $ts_end = getFormatedNumber(getMicroTimeStamp() - $ts_start, 4);
     *  - $import_end = getFormatedTimeStamp(getMicroTimeStamp() - $import_start);
     * 
     * @return  float    The Unix timestamp
     */
    public static function getMicroTimeStamp(): float
    {   return microtime(true);     }

    /**
     * Formats a number.
     * 
     * IE: 3000 = 3,000 or 45689.5670 = 45,689.56
     * 
     * @param   mixed   $number     The number needing to be formatted.
     * @param   int     $precision  The number of decimal places to leave.
     *
     * @return  string  The formatted number as a string.
     */
    public static function getFormattedNumber($number, $precision = 2): string
    {   return number_format(floatval($number), $precision, ".", ",");     }

    /**
     * Formats seconds into a 'H:M:S.ms' timestamp string
     * 
     * IE: 360 seconds = 00:06:00.00
     * 
     * @param   mixed   $seconds_input  The seconds needing to be formatted.
     *
     * @return  string  The formatted timestamp.
     */
    public static function getFormattedTimeStamp($seconds_input): string
    {
        $seconds_input      = number_format(floatval($seconds_input), 5, ".", "");
        list($sec, $usec)   = explode(".", $seconds_input);
        
        $minutes_whole = (int) ($sec / 60); // Whole Minutes (Used for Calculations Only)
        $milliseconds  = (int) ($usec);
        $seconds       = (int) ($sec % 60);
        $minutes       = (int) ($minutes_whole % 60);
        $hours         = (int) ($minutes_whole / 60);

        if($hours   < 10) { $hours   = "0" . $hours;    }
        if($minutes < 10) { $minutes = "0" . $minutes;  }
        if($seconds < 10) { $seconds = "0" . $seconds;  }
        $time_stamp = "{$hours}:{$minutes}:{$seconds}.{$usec}";

        return $time_stamp;
    }
    /*=====  End of TimeStamp Functions  ======*/

    /*=======================================
    =            Class Destroyer            =
    =======================================*/
    /**
     * Destruction of the Class
     */
    public function __destruct()
    {

    }
    /*=====  End of Class Destroyer  ======*/
}
/* End of file Utilities.php */
/* Location: /Sorcerer/Utilities/Utilities.php */