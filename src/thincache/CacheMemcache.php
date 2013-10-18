<?php

/**
 * Persist into Memcache (old php extension)
 * 
 * NOTE: Memcache vs. Memcache_d_
 * 
 * @deprecated Use the prefered CacheMemcached class instead
 * @author mstaab
 */
class CacheMemcache extends CacheAbstract
{
    private static $memcache = null;
    private static $requestStats = array();
    
    private static function connect() {
        if (self::$memcache) {
            return;
        }

        self::$memcache = new Memcache();
        self::$memcache->addServer("127.0.0.1", 11211);
        
        self::$requestStats['get'] = 0;
        self::$requestStats['set'] = 0;
        self::$requestStats['del'] = 0;
        
    	// make sure connection will be closed on request shutdown 
	    register_shutdown_function(array(__CLASS__, 'close'));
    }
    
    /**
     * (non-PHPdoc)
     * @see Cache::get()
     */
    public function get($key, $default = null) {
        $this->connect();
        
        $key = $this->cacheKey($key);
        
        self::$requestStats['get']++;
        $val = self::$memcache->get( $key );
        
        if ($val !== false) {
            return $val;
        }
        
        return $default;
    }

    /**
     * (non-PHPdoc)
     * @see Cache::set()
     */
    public function set($key, $value, $expire) {
        $this->connect();
        
        $key = $this->cacheKey($key);
        
        // check for bools. when getting we could not differentiate between a "false" returned from cache and a "false" which signals an error while getting
        if (is_bool($value)) {
            throw new Exception("Memcache does not support storage of boolean values!");
        }
		
        self::$requestStats['set']++;
        self::$memcache->set($key, $value, false, $this->calcTtl($expire));
    }
    
    /**
     * (non-PHPdoc)
     * @see Cache::set()
     */
    public function delete($key) {
        $this->connect();
        
        $key = $this->cacheKey($key);
		
        self::$requestStats['del']++;
        self::$memcache->delete($key);
    }
    
    public function supported() {
        return class_exists('Memcache', false);
    }
    
    public function getStats() {
        $this->connect();
        $stats = array();
        
        $memStats = self::$memcache->getStats();
        $stats['hits']   = $memStats['get_hits'];
        $stats['misses'] = $memStats['get_misses']; 
        $stats['size']   = $memStats['bytes'];
        $stats['more']   = 'r/w/d='. self::$requestStats['get'] . '/'.self::$requestStats['set']. '/'.self::$requestStats['del'];
                
        return $stats;
    }
    
    /**
     * Not for public use, just intended for automatic closing of the connection
     */
    public function close() {
         if (self::$memcache) {
             self::$memcache->close();
         }       
    }
}