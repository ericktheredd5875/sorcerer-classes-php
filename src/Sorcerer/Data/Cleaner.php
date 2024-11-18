<?php

namespace Sorcerer\Data;

class Cleaner
{
	/*========================================
	=            Class Attributes            =
	========================================*/
	private static $self;

	private $string_black_list;
	private $string_black_list_loaded;

	private $word_characters;
	private $word_characters_loaded;

	private $special_characters;
	private $special_characters_loaded;

	private $sql_black_list;
	private $sql_black_list_loaded;

	private $sql_black_list_regex = array();
	/*=====  End of Class Attributes  ======*/

	/*==========================================
	=            Class Initializers            =
	==========================================*/
	/**
     * Initialize the Class
     */
	function __construct()
	{
		self::$self = $this;

		$this->string_black_list         = array();
		$this->string_black_list_loaded  = false;
		
		$this->word_characters           = array();
		$this->word_characters_loaded    = false;
		
		$this->special_characters        = array();
		$this->special_characters_loaded = false;
		
		$this->sql_black_list            = array();
		$this->sql_black_list_loaded     = false;
		
		$this->sql_black_list_regex      = array();

		return $this;
	}

	/**
	 * Get class instance
	 * @return [type] [description]
	 */
	public static function getCleaner()
	{
		if(!isset(self::$self))
		{
			$_obj = __CLASS__;
			new $_obj;
		}

		return self::$self;
	}
	/*=====  End of Class Initializers  ======*/


	/*=============================================
	=            Data Clean-Up Methods            =
	=============================================*/
	public function checkSqlBlackList($data)
	{
		$this->loadSqlBlackList();

		$data = preg_replace($this->sql_black_list_regex, "", $data);

		return str_ireplace($this->sql_black_list, "", $data);
	}

	public function checkStringBlackList($data)
	{
		$this->loadStringBlackList();
		return str_ireplace($this->string_black_list, "", $data);
	}


	// FILTER_FLAG_NO_ENCODE_QUOTES: single (') and double (") quotes 
	// 									will not be encoded.
	// FILTER_FLAG_STRIP_LOW: Strips characters that have a numerical value <32.
	// FILTER_FLAG_STRIP_HIGH: Strips characters that have a numerical value >127.
	// FILTER_FLAG_STRIP_BACKTICK: Strips backtick characters.
	// FILTER_FLAG_ENCODE_LOW: Encodes all characters with a numerical value <32.
	// FILTER_FLAG_ENCODE_HIGH: Encodes all characters with a numerical value >127.
	// FILTER_FLAG_ENCODE_AMP: Encodes ampersands (&).
	public function cleanString(string $data, bool $strip = false)
	{
		if(function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) 
		{ $data = stripslashes($data); }

		$data = $this->checkStringBlackList($data);
		$data = $this->checkSqlBlackList($data);

		$data = htmlspecialchars($data, ENT_NOQUOTES);
		// $data = filter_var($data, FILTER_SANITIZE_STRING, 
												// FILTER_FLAG_NO_ENCODE_QUOTES);

		if(true === (bool) $strip)
		{
			$data = preg_replace("/\s+/", " ", $data);
			$data = preg_replace("/[^a-zA-Z0-9\- ]/", "", $data);
        }

		return $data;
	}

	public function cleanHtml(string $data)
	{
		if(get_magic_quotes_gpc()) { $data = stripslashes($data); }

		$data = $this->checkSqlBlackList($data);
		$data = filter_var($data, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		return $data;
	}

	public function cleanNumber($data)
	{
		$return_value = 0;

		if(is_numeric($data))
		{
			$data = 0 + $data;
			if(is_float($data))
			{	$return_value = $this->cleanFloat($data);	}
			else
			{	$return_value = $this->cleanInt($data);		}
		}

		return  $return_value;
	}

	public function cleanInt($data, $min = 0, $max = 0)
	{
		$int_option = [];
		if(0 <= $min && 0 < $max)
		{
			$int_option = array("options" =>
							array(
									"default"   => $min,
									"min_range" => $min,
									"max_range" => $max));
		}

		$data = filter_var($data, FILTER_SANITIZE_NUMBER_INT);
		$data = filter_var($data, FILTER_VALIDATE_INT, $int_option);

		return $data;
	}

	public function cleanFloat($data)
	{
		$data = filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT,
									FILTER_FLAG_ALLOW_FRACTION);
		$data = filter_var($data, FILTER_VALIDATE_FLOAT);

		return $data;
	}

	// Returns TRUE for "1", "true", "on" and "yes".
	// Returns FALSE otherwise.
	public function cleanBool($data)
	{
		return filter_var($data, FILTER_VALIDATE_BOOLEAN);
	}


	public function cleanFileName($data)
	{
		$this->loadSpecialCharacters();

		return  str_replace(array_keys($this->special_characters),
						array_values($this->special_characters),
						$data);
	}

	public function cleanUnderscore($data)
	{
		return str_replace("_", " ", $data);
	}

	/**
	 * Sanitizes then Validates an email address.
	 * FILTER_SANITIZE_EMAIL: Remove all characters except letters, 
	 * 							digits and !#$%&'*+-=?^_`{|}~@.[].
	 * FILTER_VALIDATE_EMAIL: Validates whether the value is a valid e-mail address.
	 * 							In general, this validates e-mail addresses 
	 * 							against the syntax in RFC 822.
	 * @param  string $data Email address to be Sanitized and Validated
	 * @return string       Cleaned and Validated email address. 
	 *                      -- If the email address fails to be 
	 *                      	Sanitized or Validated, 
	 *                      	returns FALSE
	 */
	public function cleanEmailAddress($data)
	{
		$data = filter_var($data, FILTER_SANITIZE_EMAIL);
		$data = filter_var($data, FILTER_VALIDATE_EMAIL);

		return $data;
	}

	public function cleanDomain($data)
	{
		$data = filter_var($data, FILTER_SANITIZE_URL);
		$data = filter_var($data, FILTER_VALIDATE_DOMAIN);

		return $data;
	}

	public function cleanUrl($data)
	{
		$data = filter_var($data, FILTER_SANITIZE_URL);
		$data = filter_var($data, FILTER_VALIDATE_URL);

		if(false === stripos($data, "http:")
			&& false === stripos($data, "https:"))
		{	$data = false;		}

		return $data;
	}

	public function cleanIPAddress($data)
	{
		/*if(false === filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
			&& false === filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		{
			return $data;
		}*/

		$data = filter_var($data, FILTER_VALIDATE_IP);

		return $data;
	}

	public function cleanArray(array $data, $data_type = "string")
	{
		$filtered_output = array();
		foreach($data as $key => $value)
		{
			if(is_array($value))
            {   $filtered_output[$key] = $this->cleanArray($value);    }
			else if(is_numeric($value) || "numeric" === $data_type)
			{	$filtered_output[$key] = $this->cleanNumber($value);	}
			else
			{	$filtered_output[$key] = $this->cleanString($value);	}
		}

		return  $filtered_output;
	}

	/**
	 * Transform a string into a URL Friendly version of itself
	 * @param  string $data String to be transformed.	
	 * @return string       URL Friendly version of $data.
	 */
	public function cleanAlias(string $data)
    {
		$_alias = trim(strtolower($data));
		$_alias = html_entity_decode($_alias, ENT_QUOTES);

		$_alias = preg_replace("/\s+/", 			"-", $_alias);
		$_alias = preg_replace("/\-+/", 			"-", $_alias);
		$_alias = preg_replace("/[^a-zA-Z0-9\- ]/", "",  $_alias);

		return $_alias;

		//$find_chars    = array("/[^a-zA-Z0-9\- ]/", "/\s+/", "/\-+/");
		//$replace_chars = array("", " ", "-");
		
		// $page_alias    = html_entity_decode($data, ENT_QUOTES);
		
		// $page_alias    = preg_replace("/[^a-zA-Z0-9\- ]/", "", $page_alias);
		// $page_alias    = preg_replace("/\s+/", " ", $page_alias);
		// $page_alias    = preg_replace("/\-+/", "-", $page_alias);
		
		// $page_alias    = trim(strtolower($page_alias));
		// $page_alias    = str_replace(" ", "-", $page_alias);

  //       return $page_alias;
    }
	/*=====  End of Data Clean-Up Methods  ======*/


	/*================================================================
	=            Collections of Checked Chars and Strings            =
	================================================================*/
	private function loadStringBlackList()
	{
		if(true === $this->string_black_list_loaded)
		{	return $this;							}

		$this->string_black_list = array();

		//----------------------------------------------
		// Just load black list for website searching
		//----------------------------------------------
		$current_script     = explode("/",$_SERVER["PHP_SELF"]);
		$current_script     = array_pop($current_script);
		$black_list_scripts = array("index.php");

		if(in_array($current_script, $black_list_scripts))
		{
			$this->string_black_list = array(
					/*"select",
					"insert",
					"update",
					"delete",
					"union",*/
					"truncate",
					"drop table",
					"alter table",
					"order by",
					"DECLARE",
					"VARCHAR(",
					"CAST(",
					"EXEC(",
					"<script",
					"<s",
					"</script>",
					"</s",
					"src=",
					".asp",
					".aspx",
					".php",
					".js",
					".pl",
					".cgi",
					".html",
					".htm",
					".cfm",
					"http://",
					"document.write",
					"<iframe",
					"</iframe>",
					"\\",
				);
		}

		$this->string_black_list_loaded = true;

		return $this;
	}


	private function loadWordCharacters()
	{
		if(true === $this->word_characters_loaded)
		{	return $this;							}

		$this->word_characters["?"]   = '&mdash;';
		$this->word_characters["?"]   = "'";
		$this->word_characters["?"]   = "'";
		$this->word_characters["?"]   = '"';
		$this->word_characters["?"]   = '"';
		$this->word_characters["?"]   = '...';
		$this->word_characters["?"]   = '&deg;';
		$this->word_characters["?"]   = '&frac12;';
		$this->word_characters["?"]   = '&frac34;';

		$this->word_characters_loaded = true;

		return $this;
	}

	private function loadSpecialCharacters()
	{
		if(true === $this->special_characters_loaded)
		{	return $this;							}

		$this->special_characters[" "]   = "_";
		$this->special_characters["!"]   = "_";
		$this->special_characters["@"]   = "_";
		$this->special_characters["#"]   = "_";
		$this->special_characters["$"]   = "_";
		$this->special_characters["%"]   = "_";
		$this->special_characters["^"]   = "_";
		$this->special_characters["&"]   = "_";
		$this->special_characters["*"]   = "_";
		$this->special_characters["("]   = "_";
		$this->special_characters[")"]   = "_";
		$this->special_characters["+"]   = "_";
		$this->special_characters["'"]   = "_";
		$this->special_characters['"']   = "_";
		$this->special_characters["?"]   = "_";
		$this->special_characters["<"]   = "_";
		$this->special_characters[">"]   = "_";
		$this->special_characters["/"]   = "_";
		$this->special_characters["\\"]  = "_";
		$this->special_characters["|"]   = "_";
		$this->special_characters["["]   = "_";
		$this->special_characters["]"]   = "_";
		$this->special_characters["{"]   = "_";
		$this->special_characters["}"]   = "_";
		$this->special_characters[":"]   = "_";
		$this->special_characters[";"]   = "_";
		$this->special_characters[","]   = "_";
		$this->special_characters["`"]   = "_";

		$this->special_characters_loaded = true;

		return $this;
	}

	private function loadSqlBlackList()
	{
		if(true === $this->sql_black_list_loaded)
		{	return $this;							}

		/*
		"union", "select", "delete", "insert",
			"limit", "/*", "CAST(", "EXEC(",
			"DECLARE", "FETCH", "CREATE PROCEDURE", "BEGIN",
			"END", "REPEAT", "END REPEAT", "OPEN",
			"END IF", "VARCHAR(", "CHAR(", "';", "CONCAT(", "0x27",
			"*", "all", "All", "null", "NULL", "",
			"truncate", "drop table", "alter table", "order by",
		 */

		$this->sql_black_list = array(
				"/*",
				"*/",
				"CAST(",
				"EXEC(",
				"CREATE PROCEDURE",
				"BEGIN DECLARE",
				"BEGIN FETCH",
				"BEGIN OPEN",
				"CURSOR FOR",
				"END;",
				"END REPEAT",
				"END IF",
				"VARCHAR(",
				"CHAR(",
				"';",
				"CONCAT(",
				"0x27",
				//"null", > Moved to RegEx version
				"truncate",
				"drop table",
				"alter table",
			);

		$this->sql_black_list_regex = array(
				/*
					Checks for 'null' by itself. (Ignores Case)
					IGNORING 'null' within a word (IE: Conconully, disannulled);
				 */
				"/null\b/i", 
			);

		$this->sql_black_list_loaded = true;

		return $this;
	}
	/*=====  End of Collections of Checked Chars and Strings  ======*/

	function __destruct()
	{
		unset($this->string_black_list);
		unset($this->word_characters);
		unset($this->special_characters);
	}
}
