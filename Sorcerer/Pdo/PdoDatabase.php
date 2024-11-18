<?php
/**
 * undocumented class
 *
 * @package default
 * @author
 **/

namespace Sorcerer\Pdo;

use Sorcerer\Pdo\PdoDatabaseException;

class PdoDatabase
{
	const CONN_NAME_PREFIX     = "PDODB-";
	const BINDING_PREFIX       = ":";
	const DEFAULT_CHAR_SET     = "UTF8";
	//const DEFAULT_CHAR_SET     = "latin1";

	private $conn_char_set     = "";
	private $credentials       = "";

	private $_cache            = "";
	private $default_expire    = 300;
	private $cache_token       = "";
	private $token_hash        = "";
	private $item_expire       = 0;
	private $cache_item        = array();
	private $cache_set_details = array();
	private $re_cache          = false;

	private static $db_instance;

	private $memory_debug      = false;
	private $db_log;

	private $db_options        = array();

	/**
	 * [$dsn description]
	 * @var array
	 */
	private $dsn = array();

	/**
	 * Allows multple database connections
	 * probably not used very often by many applications,
	 * but still useful
	 */
	private $connections = array();

	private $connection_retry = 0;

	/**
	 * Tells the DB object which connection to use
	 * setActiveConnection($id) allows us to change this
	 */
	private $activeConnection = 0;

	/**
	 * Queries which have been executed and then
	 * "saved for later"
	 */
	private $dataCache = array();

	/**
	 * Record of the last query
	 */
	private $lastQuery;

	private $currentQueryString;

	/**
	 * [$error description]
	 * @var [type]
	 */
	private $error;
	private $error_count = 0;
	private $error_collection = array();

	public $queryCount = 0;
	public $queries = array();
	private $currentBindings = array();

	private $query_collection = array();

	/**
	 * [__construct description]
	 */
	public function __construct()
	{
		self::$db_instance = $this;

		$this->setConnectionCharSet()
			->setDbOptions("")
			->resetCacheVars();
	}

	public static function getDB()
	{
		if(!isset(self::$db_instance))
		{
			$_obj = __CLASS__;
			new $_obj;
		}

		return self::$db_instance;
	}


	public function openMemCacheConnection($type = "memcache", $host = "", $port = "", $_expire = 0)
	{
		if(!empty($_expire)) { $this->setDefaultExpire($_expire); }

		$this->_cache = Hoard::getCache($type, $host, $port);
		$connected = $this->_cache->hasConnection();
		if(empty($connected))
		{	$this->_cache = $connected;		}

		$this->resetCacheVars();

		return $this;
	}

	private function setDefaultExpire($_expire)
	{
		$this->default_expire = $_expire;

		return $this;
	}

	private function setItemExpire($_expire)
	{
		$this->item_expire = $_expire;
		return $this;
	}

	private function calculateUnixExpire($expire)
	{
		$_ts = new \DateTime();
        $_ts->add(new \DateInterval("PT{$expire}S"));
        return $_ts->getTimestamp();
	}

	public function setMcItem($key, $value, $expire)
	{
		if(empty($this->_cache)) { return $this; }

		if(!empty($this->re_cache) && true === $this->re_cache)
		{	return array();		}

		//$_expire = $this->calculateUnixExpire($expire);

		$this->_cache->set($key, $value, 0, $expire);

		$this->cache_set_details = array(
									"token_hash" => $key,
									"data"       => $value,
									"expire"     => $expire
									);

		return $this;
	}

	public function getMcItem($key)
	{
		if(empty($this->_cache)) { return array(); }

		if(!empty($this->re_cache) && true === $this->re_cache)
		{	return array();		}

		$_cache_item = $this->_cache->get($key);

		if(!empty($_cache_item))
		{
			$this->cache_item = $_cache_item;

			$this->cache_set_details = array(
									"token_hash" => $key,
									"data"       => $_cache_item,
									"expire"     => "N/A"
									);
		}

		return $_cache_item;
	}

	public function setCacheToken($token, $_expire = 0, $re_cache = 0)
	{
		if(!empty($re_cache)) { $this->re_cache = true; }

		if(empty($_expire)) { $this->setItemExpire($this->default_expire); }
		else { $this->setItemExpire($_expire); }

		$this->cache_token = $token;
		return $this;
	}

	public function getCacheSetDetails()
	{
		return $this->cache_set_details;
	}

	private function setTokenHash()
	{
		$_cache_token = "";

		if(!empty($this->cache_token))
		{
			$_cache_token = $this->cache_token
							. ":"
							. serialize($this->currentBindings);

			$this->token_hash = md5($_cache_token);
		}
		else if(empty($this->cache_token))
		{	$this->token_hash = "";			}

		return $this;
	}

	private function setCacheItem($data, $type = "reset")
	{
		$type = strtolower($type);
		switch($type)
		{
			case "reset":
					//echo "<br><h1>RESET Cache-Item</h1><br>";
					$this->cache_item = array();
					break;

			case "count":
					//echo "+ Setting Cache-Item Count: {$data}<br>";
					$this->cache_item["count"] = $data;
					break;

			case "data":
					//$d_count = count($data);
					//echo "+ Setting Cache-Item Data: {$d_count}<br>";
					$this->cache_item["data"] = $data;
					break;
		}

		return $this;
	}

	private function resetCacheVars()
	{
		$this->setCacheToken("")
			->setTokenHash()
			->setCacheItem("");

		return $this;
	}

	private function checkForCache($fetch_type)
	{
		if(!empty($this->cache_token))
		{
			//echo "+ Query: {$this->getCurrentQueryString()}<br>";
			//echo "+ Cache Token: {$this->cache_token}<br>";
			if(empty($this->token_hash)) { $this->setTokenHash(); }

			//echo "+ Token Hash: {$this->token_hash}<br>";
			$query_info = $this->getMcItem($this->token_hash);
			if(!empty($query_info))
			{
				if("count" === $fetch_type)
				{
					$_count = $this->cache_item["count"];
					$this->setCacheItem($_count, "count");

					//echo "+ Pulling Count from Cache: {$_count}<br>";
					return true;
				}
				else
				{
					$_data = $this->cache_item["data"];
					$this->setCacheItem($_data, "data");

					//echo "+ Pulling Data from Cache: {$_data}<br>";
					return true;
				}
			}
		}

		return false;
	}

	private function cacheResultSet()
	{
		if(!empty($this->token_hash))
		{
			$this->setMcItem($this->token_hash,
							$this->cache_item,
							$this->item_expire);

		}

		$this->resetCacheVars();

		return $this;
	}

	/*======================================
	=            Setter Methods            =
	======================================*/

	/**
	 * [setDbOptions description]
	 *
	 * Possible Options:
	 * -- PDO::ATTR_PERSISTENT               => true
	 * -- PDO::ATTR_EMULATE_PREPARES         => false
	 * -- PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
	 * -- PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC
	 * -- PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION
	 *
	 * @param [type] $options [description]
	 */
	public function setDbOptions($options)
	{
		if(empty($options))
		{
			$this->db_options[\PDO::ATTR_ERRMODE]            = \PDO::ERRMODE_EXCEPTION;
			$this->db_options[\PDO::ATTR_DEFAULT_FETCH_MODE] = \PDO::FETCH_ASSOC;
			//$this->db_options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8";
			//$this->db_options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES latin1";
		}

		return $this;
	}

	/*
		$dbhost,
		$dbname,
		$user,
		$pass,
		$name,
	public function setDbHost($value)
	{
		$this->
	}
	*/

	public function setConnectionCharSet($charset = "")
	{
		if(empty($charset)) { $charset = self::DEFAULT_CHAR_SET; }
		$this->conn_char_set = $charset;

		return $this;
	}

	private function buildDbDSN($dbhost, $dbname)
	{
		if(empty($this->conn_char_set)) { $this->setConnectionCharSet(); }
		if(empty($dbhost))
		{	throw new Exception("dbhost can NOT be empty!", E_ERROR);		}

		$mysql_dsn = "mysql:host={$dbhost};"
					. "dbname={$dbname};"
					. "charset={$this->conn_char_set}";

		return $mysql_dsn;
	}


	public function setLoggingFile($_log_name)
    {
        //Logger::configure(self::DB_LOG_CONFIG);
        $this->db_log = Sorcerer\Logging\LogWriter::getLogger($_log_name);
        return $this;
    }
	/*=====  End of Setter Methods  ======*/


	/*=============================================
	=            DB Connection Methods            =
	=============================================*/
	/**
	 * [connectionOptions description]
	 * @return [type] [description]
	 */
	public function connectionOptions()
	{

	}

	/**
	 * [setActiveConnection description]
	 * @param [type] $id [description]
	 */
	public function setActiveConnection($id)
	{
		if(!$this->checkForConnection($id)
			&& $this->checkForCredentials($id))
		{
			$this->activeConnection = $id;
			$this->openDbConnection();
		}
		else
		{
			$this->activeConnection = $id;
		}

		return $this;
	}

	/**
	 * [getActiveConnection description]
	 * @return [type] [description]
	 */
	public function getActiveConnection()
	{
		return $this->activeConnection;
	}

	public function checkForConnection($id)
	{
		return array_key_exists($id, $this->connections);
	}

	public function checkForCredentials($id)
	{
		return array_key_exists($id, $this->dsn);
	}

	/**
	 * [killConnection description]
	 * @param  [type] $id [description]
	 * @return [type]     [description]
	 */
	public function killConnection($id)
	{
		$this->connections[$id] = null;
		unset($this->connections[$id]);
		return $this;
	}

	public function prepConn($dbhost, $dbname, $name = "")
	{
		if(empty($name))
		{
			$connection_count = count($this->connections);
			$name = self::CONN_NAME_PREFIX . ($connection_count + 1);
		}

		$this->dsn[$name]["dsn"] = $this->buildDbDSN($dbhost, $dbname);

		return $this;
	}

	/**
	 * [createConnection description]
	 * @param  [type] $dbhost  [description]
	 * @param  [type] $dbname  [description]
	 * @param  [type] $user    [description]
	 * @param  [type] $pass    [description]
	 * @param  [type] $options [description]
	 * @return [type]          [description]
	 */
	public function createConnection($dbhost,
										$dbname,
										$user,
										$pass,
										$name = "",
										$options = "")
	{
		$connection_count = count($this->connections);

		if(empty($name)) { $name = $connection_count + 1; }

		//$mysql_dsn = "mysql:host={$dbhost};dbname={$dbname};charset=UTF8";

		$mysql_dsn = "mysql:host={$dbhost};"
					. "dbname={$dbname};"
					. "charset={$this->conn_char_set}";

		$this->dsn[$name]["dsn"]     = $mysql_dsn;
		$this->dsn[$name]["user"]    = $user;
		$this->dsn[$name]["pass"]    = $pass;
		$this->dsn[$name]["options"] = $this->db_options;

		if(empty($connection_count))
		{
			$this->activeConnection = $name;
			$this->openDbConnection();
		}

		return $this;
	}

	private function openDbConnection()
	{
		try
		{
			@$this->connections[$this->activeConnection] = new \PDO(
					$this->dsn[$this->activeConnection]["dsn"],
					$this->dsn[$this->activeConnection]["user"],
					$this->dsn[$this->activeConnection]["pass"],
					$this->dsn[$this->activeConnection]["options"]);

			if(00000 != $this->connections[$this->activeConnection]->errorCode())
			{
				$error_code = $this->connections[$this->activeConnection]->errorCode();
				$error_info = $this->connections[$this->activeConnection]->errorInfo();
				$msg = "[SQLSTATE: {$error_info[0]}][MySQL: {$error_info[1]}] "
						. "MSG: {$error_info[2]}";

				//throw new Exception($msg, E_ERROR);
				throw new PdoDatabaseException($msg, (int) $error_code);
			}
		}
		catch(PDOException $e)
		{
			// 'SQLSTATE[HY000] [1130] Host '10.209.128.240'
			// is not allowed to connect to this MySQL server
			if(("HY000" === $e->getCode()
				|| 1130 === (int) $e->getCode())
				&& 5 >= $this->connection_retry)
			{
				sleep(1);
				$this->connection_retry++;
				return $this->openDbConnection();
			}

			if(!empty($_REQUEST["debug"]) && "giveit2me" === $_REQUEST["debug"])
			{
				echo $e->getCode() . "\n";
				echo $e->getMessage() . "\n";
				print_r($this->dsn[$this->activeConnection]);
			}

			throw new \Exception($e->getMessage(), $e->getCode());
			//throw new PdoDatabaseException($e->getMessage(), $e->getCode());
			return FALSE;
		}
		catch(\Exception $e)
		{
			if(!empty($_REQUEST["debug"]) && "giveit2me" === $_REQUEST["debug"])
			{
				echo $e->getMessage() . "\n";
				print_r($this->dsn[$this->activeConnection]);
			}

			throw new \Exception($e->getMessage(), $e->getCode());
			return FALSE;
		}

		$this->connection_retry = 0;
		return true;
	}
	/*=====  End of DB Connection Methods  ======*/


	public function addStoredQuery($key, $query)
	{
	    $this->query_collection[$key] = $query;
	    return $this;
	}

	public function setStoredQuery($key)
	{
	    if(empty($this->query_collection[$key]))
	    {
	    	$msg = "'{$key}' is not in the Query Collection!!!";
	    	throw new \Exception($msg, E_WARNING);
	    }

	    $this->clearQueryBindings()
	            ->setQuery($this->query_collection[$key]);
	    return $this;
	}







	/**
	 * [setCurrentQueryString description]
	 * @param [type] $query [description]
	 */
	public function setCurrentQueryString($query)
	{
		$this->currentQueryString = $query;
		return $this;
	}

	/**
	 * [getCurrentQueryString description]
	 * @return [type] [description]
	 */
	public function getCurrentQueryString()
	{
		return $this->currentQueryString;
	}

	/**
	 * [setQueryBindings description]
	 */
	private function setQueryBindings($key, $value, $type)
	{
		$this->currentBindings[$key] = array(
								"key"   => $key,
								"value" => $value,
								"type"  => $type
								);
		return $this;
	}

	/**
	 * [clearQueryBindings description]
	 * @return [type] [description]
	 */
	private function clearQueryBindings()
	{
		$this->currentBindings = array();
		return $this;
	}

	/**
	 * [getQueryBindings description]
	 * @return [type] [description]
	 */
	private function getQueryBindings()
	{	return $this->currentBindings;		}

	/**
	 * [getError description]
	 * @return [type] [description]
	 */
	public function getError() { return $this->error->getMessage(); }

	/**
	 * [getErrorCode description]
	 * @return [type] [description]
	 */
	public function getErrorCode() { return $this->error->getCode(); }

	/**
	 * [getErrorTrace description]
	 * @return [type] [description]
	 */
	public function getErrorTrace() { return $this->error->getTrace();	}

	public function getParamDump()	{ return $this->lastQuery->debugDumpParams();	}

	public function doSimpleQuery($sql, $response_type = "all")
	{
		if(empty($response_type)) { $response_type = "all"; }

		if(!empty($sql))
		{
			$this->setQuery($sql);
			return $this->getResultSet($response_type);
		}

		return false;
	}

	/**
	 * [setQuery description]
	 * @param [type] $query [description]
	 */
	public function setQuery($query)
	{
		$this->clearQueryBindings()->setCurrentQueryString($query);

		try
		{
			//$options = array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL);
			$options = array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY);
			$_active_connection = $this->connections[$this->activeConnection];
			$this->lastQuery = $_active_connection->prepare($query, $options);
		}
		catch(PDOException $e)
		{
			throw new Exception($e->getMessage(), $e->getCode());
		}

		return $this;
	}

	/*================================================
	=            Variable Binding Methods            =
	================================================*/
	/**
	 * Set binding vars for the PDO::prepare
	 * @param String $param The parameter used in the binding Example: ':name'
	 * @param Mixed $value The actual value to be used Example: 'John Smith'
	 * @param String $type  The datatype of the parameter. Example: 'string'
	 */
	public function setBinding($param, $value, $type = null)
	{
		if(is_array($value))
		{
			$msg = "Use PdoDatabase->setBindings() to specify an array "
					. "of 'key'=>'value' pairs.";
			throw new \Exception($msg, E_ERROR);
		}

		if(":limit" === $param
				|| ":offset" === $param)
		{
			$value = intval(trim($value));
			$type  = \PDO::PARAM_INT;
		}
		else if(is_null($type))
		{
			switch(true)
			{
				case is_int($value): 	$type = \PDO::PARAM_INT; 	break;
				case is_bool($value): 	$type = \PDO::PARAM_BOOL; 	break;
				case is_null($value): 	$type = \PDO::PARAM_NULL; 	break;
				default:				$type = \PDO::PARAM_STR; 	break;
			}
		}


		$this->setQueryBindings($param, $value, $type);
		$this->lastQuery->bindValue($param, $value, $type);

		return $this;
	}

	/**
	 * Using an array collection,
	 * 		creates the required Binding Key to Value pairs
	 * @param array $bindings [description]
	 */
	public function setBindings(array $bindings)
	{
		$binding_count = count($bindings);
		if(0 < $binding_count)
		{
			foreach($bindings as $field => $value)
			{
				$posIndex = strpos($field, ":");
				if(false !== $posIndex && 0 == $posIndex)
				{	$this->setBinding($field, $value);			}
				else
				{	$this->setBinding(":{$field}", $value);		}
			}
		}

		return $this;
	}


	public function setBindingCollection(array $collection)
	{
		$collection_count = count($collection);
		if(0 < $collection_count)
		{
			foreach($collection_count as $binder => $value)
			{
				$this->setBinding($binder, $value);
			}
		}

		return $this;
	}

	/**
	 * [buildBindings description]
	 * @param  [type] $type       [description]
	 * @param  array  $collection [description]
	 * @return [type]             [description]
	 *
	 * Field Collection 	= array("field" => "value");
	 * Condition Collection = array("field" => array("value", "condition"));
	 * Between Collection 	= array("field" => array("value", "condition"));
	 */
	public function buildBindings(array $collection)
	{
		$_fields = array();
		$_values = array();

		//$type             = strtolower($type);
		$collection_count = count($collection);

		if(0 < $collection_count)
		{
			foreach($collection as $field => $value)
			{
				$condition = "=";
				$field = trim($field);

				if(empty($field)) { continue; }
				else if(is_array($value))
				{
					list($_value, $_condition) = $value;

					if(!empty($_condition))
					{ 	$condition = $_condition; 	}

					$value = $_value;
				}

				$condition = strtoupper($condition);
				if("LIKE" === $condition)
				{
					$value     = "%{$value}%";
				}

				$binder           = ":{$field}";
				$_fields[$binder] = "{$field} {$condition} {$binder}";
				$_values[$binder] = $value;
			}
		}

		return array($_fields, $_values);
	}
	/*=====  End of Variable Binding Methods  ======*/

	public function showMemDebug()
	{
		$this->memory_debug = true;
		return $this;
	}

	private function displayMemData($display_text)
	{
		$memory_byte = (float) memory_get_usage(true);
	    echo "+ [{$display_text}] MemUsed: " . $this->formatBytes($memory_byte, 2) . "\n";
	}

	private function formatBytes($size, $precision = 2)
    {
        $base = log($size) / log(1024);
        $suffixes = array('', 'k', 'M', 'G', 'T');

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

	/**
	 * [executeQuery description]
	 * @return [type] [description]
	 */
	private function executeQuery()
	{
		try
		{
			if($this->memory_debug) { $this->displayMemData(__METHOD__ . '-1'); }

			$start = $this->getTime();
			$data = $this->lastQuery->execute();

			if($this->memory_debug) { $this->displayMemData(__METHOD__ . '-2'); }

			$this->queryCount++;
			$this->logQuery($start);

			return $data;
		}
		catch(PDOException $e)
		{
			// SQLSTATE[HY000]: General error: 2006 MySQL server has gone away
			if("HY000" === $e->getCode())
			{
				//echo "++ <b>DB Connection --></b> RESET<br/>\n";
				$this->openDbConnection();

				$cur_query_string 	= $this->getCurrentQueryString();
				$cur_var_binding 	= $this->getQueryBindings();
				$this->setQuery($cur_query_string)
					->setBindings($cur_var_binding);

				return $this->executeQuery();
			}
			else //if("HY093" == $e->getCode())
			{
				$exception_code = $e->getCode();

				try
				{
					throw new PdoDatabaseException($e->getMessage(), (int) $e->getCode());
				}
				catch(PdoDatabaseException $pde)
				{
					//echo "SQLSTATE: " . $exception_code . "<br>";
					$pde->setCurrentConnection($this->getActiveConnection())
						->setCurrentQuery($this->getCurrentQueryString())
						->setCurrentBindings($this->getQueryBindings())
						->processQueryException(__METHOD__);
				}

				//exit();
			}

			//$logger->INFO('<<---------------- START: Evernet Full ---------------->>');
			/*$this->logger->WARN('<<--------------------- ::ERROR:: --------------------->>');
			$this->logger->WARN('::SQL Parameters::');

			$binding_count = count($this->currentBindings);
			if($binding_count > 0)
			{
				foreach($this->currentBindings AS $binding_key => $binding_data)
				{
					if($binding_key == ":features" || $binding_key == ":features360")
					{	$binding_data['value'] = "A LOT OF STUFF";	}

					$message = "+ {$binding_key} -- Key: {$binding_data['key']} ";
					$message .= "-- Value: {$binding_data['value']} ";
					$message .= "-- Type: {$binding_data['type']}";

					$this->logger->WARN($message);
				}

				$this->logger->WARN("+ Binding Count: {$binding_count}\n");
			}
			else
			{	$this->lastQuery->debugDumpParams();	}

			$this->logger->WARN("::-------------------------::");
			$this->logger->WARN('::SQL ERROR::');
			$this->logger->WARN("+ [" . __LINE__ . "::" . __CLASS__ . "] Error Message:");
			$this->logger->WARN("+ {$e->getMessage()}");
			$this->logger->WARN("+ MySQL Error Code: {$e->getCode()}");
			$this->logger->WARN("::-------------------------::");

			$this->logger->WARN('::SQL Query::');
			$this->logger->WARN("+ {$this->getCurrentQueryString()}");
			$this->logger->WARN("::-------------------------::");

			$this->logger->WARN('::PHP Back-Trace::');
			$this->logger->WARN("+ {$e->getTraceAsString()}");
			$this->logger->WARN('<<--------------------- ::ERROR:: --------------------->>');*/

			/*
			$_error_message = (string) $e->getMessage();
			$_temp_error 	= explode(': ', $_error_message);
			$_error_code 	= $_temp_error[0];
			$_error_message = $_temp_error[2];

			echo "<pre>\n";
			echo "<<---------------------------- ::ERROR:: ---------------------------->><br/>\n";
			echo "++ <b>LINE -----------></b> " . __LINE__ . "<br/>\n";
			echo "++ <b>Class/Method ---></b> " . __METHOD__ . "<br/>\n";
			echo "++ <b>DB CODE --------></b> {$_error_code}<br/>\n";
			echo "++ <b>DB MSG ---------></b> {$_error_message}<br/>\n";

			echo $this->getCurrentQueryString() . "\n";
			echo "++ Code: {$e->getCode()}<br>";
			//$arr = $this->connections[$this->activeConnection]->errorInfo();
			//print_r($arr);

			if($e->getCode() == 23000)
			{
				$this->setErrorCollection($e->getCode(), $e->getMessage());
				return false;
			}
			// SQLSTATE[HY093]: Invalid parameter number:
			// number of bound variables does not match number of tokens
			else if("HY093" == $e->getCode())
			{
				try
				{
					echo "+ Here I am!<br>";
					throw new PdoDatabaseException($e->getMessage(), (int) $e->getCode());
				}
				catch(PdoDatabaseException $pde)
				{
					//echo "+ Here I am again<br>";
					echo "SQLSTATE: " . $pde->getCode() . "<br>";
					echo "SQLMSG: " . $pde->getMessage() . "<br>";
					$pde->processQueryException();
				}




				echo "++ ----------------- <b>QUERY</b> ----------------- ++<br/>\n";
				echo "++ <b>String ------></b> {$this->getCurrentQueryString()}<br/>\n";
				echo "++ ---------------- <b>BINDINGS</b> --------------- ++<br/>\n";
				$cur_var_binding 	= $this->getQueryBindings();
				foreach($cur_var_binding AS $key => $data)
				{	echo "++ <b>Param --></b> {$key} :=: <b>Value --></b> {$data['value']}<br/>\n";	}

				echo "++ -------------- <b>STACK TRACE</b> -------------- ++<br/>\n";
				echo "{$e->getTraceAsString()}<br/>\n";
				echo "<<---------------------------- ::ERROR:: ---------------------------->><br/>\n";
				echo "</pre>\n";

				exit();
			}
			else
			{

				//echo "++ <b>STACK TRACE ----></b> <br/>{$e->getTraceAsString()}<br/>";
				//print_r($this->getParamDump());

				$cur_var_binding 	= $this->getQueryBindings();
				foreach($cur_var_binding AS $key => $data)
				{	echo "++ <b>Param --></b> {$key} :=: <b>Value --></b> {$data['value']}<br/>\n";	}
				//print_r($cur_var_binding);
				echo "++ -------------- <b>STACK TRACE</b> -------------- ++<br/>\n";
				echo "{$e->getTraceAsString()}<br/>\n";
				echo "<<---------------------------- ::ERROR:: ---------------------------->><br/>\n";
				echo "</pre>\n";
				exit();
			}*/
		}
	}

	/**
	 * [getResultSet description]
	 * @param  string $fetch_type [description]
	 * @return [type]             [description]
	 */
	public function getResultSet($fetch_type = "all")
	{
		$has_cache  = false;
		$query_info = "";
		$fetch_type = strtolower($fetch_type);

		if($this->memory_debug) { $this->displayMemData(__METHOD__); }

		// Check for Cache Data:
		$has_cache = $this->checkForCache($fetch_type);
		if(true === $has_cache)
		{
			if("count" === $fetch_type)
			{	return $this->cache_item["count"];		}
			else
			{
				$cache_data = $this->cache_item["data"];
				$this->resetCacheVars();
				return $cache_data;
			}
		}

		$this->lastQuery->closeCursor();
		$success = $this->executeQuery();

		if($this->memory_debug) { $this->displayMemData(__METHOD__); }

		if($success)
		{
			switch($fetch_type)
			{
				case "all":
					$query_count = $this->getRowCount();
					$query_info  = $this->lastQuery->fetchAll(\PDO::FETCH_ASSOC);

					$this->setCacheItem($query_count, 	"count")
						->setCacheItem($query_info, 	"data")
						->cacheResultSet();

					break;

				case "single":
					$query_count = $this->getRowCount();
					$query_info  = $this->lastQuery->fetch(\PDO::FETCH_ASSOC);

					$this->setCacheItem($query_count, 	"count")
						->setCacheItem($query_info, 	"data")
						->cacheResultSet();

					break;

				case "object":
					$query_count = $this->getRowCount();
					$query_info  = $this->lastQuery->fetchObject();

					$this->setCacheItem($query_count, 	"count")
						->setCacheItem($query_info, 	"data")
						->cacheResultSet();
					break;

				case "obj-all":
					$query_count = $this->getRowCount();
					$query_info  = $this->lastQuery->fetchAll(\PDO::FETCH_OBJ);

					$this->setCacheItem($query_count, 	"count")
						->setCacheItem($query_info, 	"data")
						->cacheResultSet();
					break;

				case "count":
					$query_info = $this->getRowCount();
					$this->setCacheItem($query_info, "count");
					break;

				case "insert":
					$query_info = $this->getLastInsertID();
					break;

				case "update":
					$query_info = $this->getRowCount();
					break;

				case "delete":
					$query_info = $success;
					break;
			}

			return $query_info;
		}
		else
		{
			$errors = $this->lastQuery->errorInfo();

			if($errors[0] == 23000)
			{
				$message = "[WARNING: {$errors[2]}]";
				return $message;
			}
			else
			{
				print_r($errors);
				exit();
			}
		}

		return false;
	}

	public function getResultData($fetch_type = "all")
	{
		$has_cache  = false;
		$query_info = array();
		$fetch_type = strtolower($fetch_type);

		// Check for Cache Data:
		$has_cache = $this->checkForCache($fetch_type);
		if(true === $has_cache)
		{
			//echo "<pre>Getting Cached Stuff! {$this->token_hash}</pre>";
			if("count" === $fetch_type)
			{	return $this->cache_item["count"];		}
			else
			{
				$cache_data = $this->cache_item["data"];
				$this->resetCacheVars();
				return $cache_data;
			}
		}

		if("all" === $fetch_type)
		{	$query_info = $this->lastQuery->fetchAll(\PDO::FETCH_ASSOC);		}
		else if("single" === $fetch_type)
		{	$query_info = $this->lastQuery->fetch(\PDO::FETCH_ASSOC);		}
		else if("obj-all" === $fetch_type)
		{
			$query_info = $this->lastQuery->fetchAll(\PDO::FETCH_OBJ);
		}
		else if("object" === $fetch_type)
		{	$query_info = $this->lastQuery->fetchObject();					}

		$this->setCacheItem($query_info, "data")->cacheResultSet();

		return $query_info;
	}


	public function executeProcedure($query)
	{
		$exec_result = $this->connections[$this->activeConnection]->exec($query);
		if(false === $exec_result)
		{
			print_r($this->connections[$this->activeConnection]->errorInfo());
		}

		return $exec_result;
	}

	public function getResultCollection($num_of_results = 1, $offset = 0)
	{
		if(!empty($offset))
		{
			$this->readDataForward($offset - 1);
		}

		$index = 0;
		$collection = array();
		while($result = $this->lastQuery->fetchObject())
		{
			$collection[] = $result;
			$index++;

			if($index > $num_of_results)
			{	break;		}
		}

		return $collection;
	}

	/**
	 * [readDataForward description]
	 * @return [type] [description]
	 */
	public function readDataForward($offset = 0)
	{
		if(empty($offset)) { $offset = 1; }

		try
		{
			return $this->lastQuery->fetch(\PDO::FETCH_ASSOC,
											\PDO::FETCH_ORI_NEXT,
											$offset);
		}
		catch(PDOException $e)
		{
			throw new \Exception($e->getMessage(), $e->getCode());
		}
	}

	public function readDataBack($offset = 0)
	{
		if(empty($offset)) { $offset = 1; }

		try
		{
			return $this->lastQuery->fetch(PDO::FETCH_ASSOC,
											PDO::FETCH_ORI_PRIOR,
											$offset);
		}
		catch(PDOException $e)
		{
			throw new \Exception($e->getMessage(), $e->getCode());
		}
	}

	/**
	 * [getRowCount description]
	 * @return [type] [description]
	 */
	public function getRowCount()
	{	return $this->lastQuery->rowCount();	}

	/**
	 * [getLastInsertID description]
	 * @return [type] [description]
	 */
	public function getLastInsertID()
	{	return $this->connections[$this->activeConnection]->lastInsertId();	}


	/**
	 * Sanitize data
	 * @param  String $data The data to be sanitized
	 * @return String       The sanitized data
	 */
	public function sanitizeData($data)
	{	return $this->connections[$this->activeConnection]->quote($data);	}


	public function formatQueryUsingIn($_string, $tag, $delim = '|')
	{
		if(empty($tag))
		{
			$msg = "\$tag must contain a string for Query Binding.";
			throw new \Exception($msg, E_ERROR);
		}

		if($delim != 'array')
		{	$_temp = explode($delim, $_string);		}
		else
		{	$_temp = $_string;						}

		$bindings = array();
		foreach($_temp AS $key => $value)
		{	
			if(!empty($value) || 0 === (int) $value) 
			{ 	$bindings[":{$tag}{$key}"] = $value; 	}	
		}

		return array(true, $bindings);
	}

	public function formatUsingIn($_string, $tag, $delim = '|')
	{
		list($status, $bindings) = $this->formatQueryUsingIn($_string, $tag, $delim);

		if(empty($status)) { die($bindings); }

		$sql_string = "";

		$binding_count = count($bindings);
		if(0 < $binding_count)
		{
			$fields = array_keys($bindings);
			$values = array_values($bindings);

			$sql_string = "{$tag} IN (" . implode(", ", $fields) . ")";
		}

		return array($sql_string, $bindings);
	}

	/*========================================
	=            C.R.U.D. Methods            =
	========================================*/
	/**
	 * Insert records into the database
	 * @param  String $table the database table to insert into
	 * @param  Array $data  Data to insert (field => value)
	 * @return Mixed        # of affected rows if successful/(bool) 0 or FALSE on failure
	 */
	public function insertRecords($table, $data)
	{
		$fields     = array();
		$values     = array();
		$data_count = count($data);
		if(0 < $data_count)
		{
			list($fields, $values) = $this->buildBindings($data);

			$insert_sql = "INSERT INTO {$table} SET " . implode(", ", $fields);
			$this->setQuery($insert_sql)->setBindings($values);
			return $this->getResultSet("insert");
		}


		return false;
	}

	/**
	 * Update records in the database
	 * @param  String $table     the table to update
	 * @param  Array $changes   The changes to make (field => value)
	 * @param  String $condition The conditions, in which to make the changes
	 * @return Mixed            # of affected rows if successful/(bool) 0 or FALSE on failure
	 */
	public function updateRecords($table, $changes, $conditions)
	{
		$fields       = array();
		$values       = array();
		$f_conditions = array();
		$v_conditions = array();

		$change_count = count($changes);
		if(0 < $change_count)
		{
			list($fields, $values) = $this->buildBindings($changes);
			list($f_conditions, $v_conditions) = $this->buildBindings($conditions);

			$condition_count = count($f_conditions);

			$update_sql = "UPDATE {$table}
							SET " . implode(", ", $fields);
			if(0 < $condition_count)
			{	$update_sql .= " WHERE " . implode(" AND ", $f_conditions);	}

			$this->setQuery($update_sql)->setBindings($values);
			if(0 < $condition_count)
			{	$this->setBindings($v_conditions);		}

			return $this->getResultSet("update");
		}
		else
		{	return false;	}
	}

	/**
	 * Delete from the database
	 * @param  [String] $table     [the table to remove rows from]
	 * @param  [String] $condition [the condition for which rows are to be removed]
	 * @param  [Int] $limit     [the number of rows to be removed]
	 * @return [Int]            # of affected rows if successful/(bool) 0 or FALSE on failure
	 */
	public function deleteRecords($table, $conditions, $limit = "")
	{
		$condition_count = count($conditions);
		if(empty($condition_count))
		{
			$msg = "Delete cannot have an empty condition clause. "
					. "[Table: {$table}]";
			throw new \Exception($msg, E_ERROR);
		}

		if(!empty($limit)) { $limit = " LIMIT {$limit}"; }
		else { $limit = "";	}

		$condition_sql = "";
		$f_conditions  = array();
		$v_conditions  = array();

		list($f_conditions, $v_conditions) = $this->buildBindings($conditions);
		$condition_count = count($f_conditions);
		if(0 < $condition_count)
		{	$condition_sql = " WHERE " . implode(" AND ", $f_conditions);	}


		$delete_sql = "DELETE FROM {$table}
						{$condition_sql}
						{$limit}";
		$this->setQuery($delete_sql)->setBindings($v_conditions);
		return $this->getResultSet("delete");
	}
	/*=====  End of C.R.U.D. Methods  ======*/



	/*===========================================
	=            Transaction Methods            =
	===========================================*/
	/**
	 * [beginTransaction description]
	 * @return [type] [description]
	 */
	public function beginTransaction()
	{	return $this->connections[$this->activeConnection]->beginTransaction();		}

	/**
	 * [endTransaction description]
	 * @return [type] [description]
	 */
	public function endTransaction()
	{	return $this->connections[$this->activeConnection]->commit();		}

	/**
	 * [cancelTransaction description]
	 * @return [type] [description]
	 */
	public function cancelTransaction()
	{	return $this->connections[$this->activeConnection]->rollBack();		}
	/*=====  End of Transaction Methods  ======*/

	/*================================================
	=            Error Collection Methods            =
	================================================*/
	/**
	 * [getDebugDumpParams description]
	 * @return [type] [description]
	 */
	public function getDebugDumpParams()
	{	return $this->lastQuery->debugDumpParams();		}

	private function setErrorCollection($er_code, $er_message)
    {
		$this->error_collection[$this->error_count]['errorCode'] = $er_code;
		$this->error_collection[$this->error_count]['errroMessasge'] = $er_message;

		$this->error_count++;

		return $this;
    }

    public function getErrorCollection()
    {
    	return $this->error_collection;
    }
	/*=====  End of Error Collection Methods  ======*/


	/**
	 * [__destruct description]
	 */
	public function __destruct()
	{
		foreach($this->connections AS $conn_key => $connection)
		{	$connection = null;		}
	}

	/*-----------------------------------
	          	DEBUGGING
	------------------------------------*/
	function logQuery($start)
	{
		if($this->activeConnection == 'main')
		{
			$query = array(
				'sql' => $this->getCurrentQueryString(),
				'bindings' => $this->getQueryBindings(),
				'time' => ($this->getTime() - $start)*1000
			);

			array_push($this->queries, $query);
		}
		else
		{
			$this->queryCount--;
			return '';
		}
	}

	function getTime()
	{
		$time = microtime();
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];
		$start = $time;
		return $start;
	}

	public function getReadableTime($time)
	{
		$ret = $time;
		$formatter = 0;
		$formats = array("ms", "s", "m");
		if(1000 <= $time && 60000 > $time)
		{
			$formatter = 1;
			$ret = ($time / 1000);
		}
		if(60000 <= $time)
		{
			$formatter = 2;
			$ret = ($time / 1000) / 60;
		}
		$ret = number_format($ret, 3, '.', '') . ' ' . $formats[$formatter];
		return $ret;
	}
}

/* End of file pdodatabase.class.php */
/* Location: ./library/pdodatabase.class.php */