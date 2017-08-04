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
        if (!self::$memcache->addServer(MEMCACHE_HOST, MEMCACHE_PORT)) {
            throw new CacheException('Unable to add server '. MEMCACHE_HOST .' on port '. MEMCACHE_PORT .', ResultCode:'. self::$memcache->getResultCode());
        }
        
        self::$requestStats['get'] = 0;
        self::$requestStats['set'] = 0;
        self::$requestStats['del'] = 0;
    }
    
    /**
     * @param string|CacheKey $key
     * @return string
     */
    protected function cacheKey($key) {
        $stringKey = parent::cacheKey($key);

        // memcache doesn't like spaces in cache-keys
        return str_replace(' ', '_', $stringKey);
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
     * increments a counter
     *
     * @param string|CacheKey $key
     * @param int $step
     * @param int $expire
     *
     * @return int The current value of the counter. Returns 0 when the counter has just been created.
     *
     * @since 0.9.0
     */
    public function increment($key, $step = 1, $expire) {
        $this->connect();
        
        $key = $this->cacheKey($key);
        
        self::$requestStats['set']++;
        
        $binProtocol = self::$memcache->getOption(Memcached::OPT_BINARY_PROTOCOL);
        // we need the binary protocol to get support for 4 args in increment().
        // ASCII protocoll (default) only supports 2 args (misses default_initial, expiry).
        self::$memcache->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
        $val = self::$memcache->increment($key, $step, 0, $this->calcTtl($expire));
        self::$memcache->setOption(Memcached::OPT_BINARY_PROTOCOL, $binProtocol);
        
        if (self::$memcache->getResultCode() != MemCached::RES_SUCCESS) {
            throw new CacheException('Unable to increment value using key '. $key .', ResultCode:'. self::$memcache->getResultCode() .', Error:'. self::$memcache->getResultMessage());
        }
        
        return $val;
    }
    
    /**
     * @param unknown $regexKey
     * @param number $limit
     * @throws CacheException
     * @return CacheKeyStatic[]
     *
     * @since 0.9.0
     */
    public function getRegex($regexKey, $limit = 100) {
        $this->connect();
        
        $regexKey = $this->cacheKey($regexKey);
        
        $keys = self::$memcache->getAllKeys();
        if (false === $keys) {
            throw new CacheException('Unable to fetch all keys, ResultCode:'. self::$memcache->getResultCode() .', Error:'. self::$memcache->getResultMessage());
        }
        self::$memcache->getDelayed($keys);
        if (self::$memcache->getResultCode() != MemCached::RES_SUCCESS) {
            throw new CacheException('Unable to get delayed keys, ResultCode:'. self::$memcache->getResultCode() .', Error:'. self::$memcache->getResultMessage());
        }
        
        $allEntries = self::$memcache->fetchAll();
        if (self::$memcache->getResultCode() != MemCached::RES_SUCCESS) {
            throw new CacheException('Unable to fetch all values, ResultCode:'. self::$memcache->getResultCode() .', Error:'. self::$memcache->getResultMessage());
        }
        
        self::$requestStats['get']++;
        
        $i = 0;
        $res = array();
        foreach($allEntries as $entry) {
            if (preg_match($regexKey, $entry['key'])) {
                // wrap keys into static-keys so the caller can pass those apc-keys back into the cache-api,
                // without double-prefixing/namespacing issues
                $entry['key'] = new CacheKeyStatic($entry['key']);
                $res[] = $entry;
                $i++;
                
            }
            
            if ($i == $limit) {
                break;
            }
        }
        
        return $res;
    }
    
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
