<?php
// Memcache Version

namespace Sorcerer\Hoard;

class HoardMc
{
    CONST MEMCACHE_IP = "10.210.66.82";
    CONST MEMCACHE_PORT = 11211;

    // Default TTL 300 sec = 5 min
    CONST MEMCACHE_TTL = 300;

    private $type = "";
    private $host = "";
    private $port = "";

    private $_cache = "";
    private $connected = false;

    private $ttl = 0;
    private $expiration = "";

    private static $cache_object;

    public function __construct($type = "memcache", $host = "", $port = "")
    {
        self::$cache_object = $this;

        $this->setType($type)
            ->setHost($host)
            ->setPort($port)
            ->openConnection();
    }

    /*===================================
    =            Get Methods            =
    ===================================*/
    public static function getCache($type = "memcache", $host = "", $port = "")
    {
        if(!isset(self::$cache_object))
        {
            $_obj = __CLASS__;
            new $_obj($type, $host, $port);
        }

        return self::$cache_object;
    }
    /*=====  End of Get Methods  ======*/

    /*===================================
    =            Set Methods            =
    ===================================*/
    public function setType($data = "memcache")
    {
        $this->type = $data;

        return $this;
    }

    public function setHost($data = "")
    {
        $this->host = $data;
        if(empty($data))
        {   $this->host = self::MEMCACHE_IP;    }

        return $this;
    }

    public function setPort($data = "")
    {
        $this->port = $data;
        if(empty($data))
        {   $this->port = self::MEMCACHE_PORT;  }

        return $this;
    }

    private function setConnected($data)
    {
        $this->connected = $data;

        return $this;
    }

    public function setTtl($time)
    {
        if(empty($time)) { $time = self::MEMCACHE_TTL; }

        $this->ttl = $time;
        $this->setExpiration();

        return $this;
    }

    private function setExpiration()
    {
        $_ts = new \DateTime();
        $_ts->add(new \DateInterval("PT{$this->ttl}S"));
        $this->expiration = $_ts->getTimestamp();

        return $this;
    }
    /*=====  End of Set Methods  ======*/

    /*==========================================
    =            Connection Methods            =
    ==========================================*/
    private function openConnection()
    {
        if("memcached" === $this->type)
        {
            $this->_cache = new \Memcached();
            $_connected = $this->_cache->addServer($this->host, $this->port, 100);

            //print_r($this->_cache->getVersion());
            /*
            phpFastCache::setup("memcached");
            $this->_cache = phpFastCache("memcached");

            $server = array(array($this->host, $this->port, 100));
            $this->_cache->option("server", $server);

            $_connected = true;*/
        }
        else
        {
            $this->_cache = new \Memcache;
            $_connected = $this->_cache->connect(
                                        $this->host,
                                        $this->port
                                        );
        }

        $this->setConnected($_connected);

        return $this;
    }

    public function hasConnection()
    {
        return $this->connected;
    }

    public function resetConnection()
    {
        $this->openConnection();

        return $this;
    }
    /*=====  End of Connection Methods  ======*/

    /*==============================================
    =            Memcached Interactions            =
    ==============================================*/
    public function set($key, $value, $_ttl = 0)
    {
        $this->setTtl($_ttl);

        if("memcached" === $this->type)
        {
            return $this->_cache->set($key, $value, $this->expiration);
        }
        else
        {
            return $this->_cache->set($key, $value, 0, $this->expiration);
        }

    }

    public function replace($key, $value, $_ttl = 0)
    {
        $this->setTtl($_ttl);
        $replaced = $this->_cache->replace($key, $value, 0, $this->expiration);

        if(false === $replaced)
        {   return $this->set($key, $value, $_ttl);     }

        return $replaced;
    }

    public function get($key)
    {
        return $this->_cache->get($key);
    }

    public function delete($key)
    {
        return $this->_cache->delete($key);
    }

    public function flush()
    {
        return $this->_cache->flush();
    }
    /*=====  End of Memcached Interactions  ======*/


    public function __destruct()
    {
        if("memcached" !== $this->type)
        {
            $this->_cache->close();
        }
    }
}
