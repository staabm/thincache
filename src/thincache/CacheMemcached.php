<?php

if (!defined('MEMCACHE_HOST')) {
    define('MEMCACHE_HOST', "127.0.0.1");
    define('MEMCACHE_PORT', 11211);
}

/**
 * Persist into Memcached (newer php extension)
 * 
 * NOTE: Memcache vs. Memcache_d_
 * 
 * @author mstaab
 */
class CacheMemcached extends CacheAbstract
{
    private static $memcache = null;
    private static $requestStats = array();
    
    private static function connect() {
        if (self::$memcache) {
            return;
        }

        self::$memcache = new Memcached();
        self::$memcache->addServer(MEMCACHE_HOST, MEMCACHE_PORT);
        
        self::$requestStats['get'] = 0;
        self::$requestStats['set'] = 0;
        self::$requestStats['del'] = 0;
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
        
        // support values like 0, array() or null
        if ($val === false && self::$memcache->getResultCode() != MemCached::RES_SUCCESS) {
            return $default;
        }
        
        return $val;
    }

    /**
     * (non-PHPdoc)
     * @see Cache::set()
     */
    public function set($key, $value, $expire) {
        $this->connect();
        
        $key = $this->cacheKey($key);
		
        self::$requestStats['set']++;
        if (self::$memcache->set($key, $value, $this->calcTtl($expire)) === false) {
            throw new CacheException('Unable to set value using key '. $key .', ResultCode:'. self::$memcache->getResultCode() .', Error:'. self::$memcache->getResultMessage());
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see Cache::set()
     */
    public function delete($key) {
        $this->connect();
        
        $key = $this->cacheKey($key);
		
        self::$requestStats['del']++;
        if (self::$memcache->delete($key) === false) {
            if (self::$memcache->getResultCode() != Memcached::RES_NOTFOUND) {
                throw new CacheException('Unable to delete value using key '. $key .', ResultCode:'. self::$memcache->getResultCode() .', Error:'. self::$memcache->getResultMessage());
            }
        }
    }
    
    public function supported() {
        return class_exists('Memcached', false);
    }
    
    public function getStats() {
        $this->connect();
        $stats = array();
        
        // Memcached returns an array of stats, we just use one server -> use first stats
        $memStats = current(self::$memcache->getStats());
        
        $stats['hits']   = $memStats['get_hits'];
        $stats['misses'] = $memStats['get_misses']; 
        $stats['size']   = $memStats['bytes'];
        $stats['more']   = 'r/w/d='. self::$requestStats['get'] . '/'.self::$requestStats['set']. '/'.self::$requestStats['del'];
                        
        return $stats;
    }
    
    /**
     * Returns a Memcache instance. Will try certain fallbacks to get a working implementation 
     * 
     * @return CacheInterface
     */
    public static function factory() {
        $memcached = new CacheMemcached();
        if ($memcached->supported()) {
            return $memcached;
        } else {
            $fallback = new CacheMemcache();
            if ($fallback->supported()) {
                return $fallback;
            }
        }
        throw new Exception('Missing memcached php-extension');
    }
}
