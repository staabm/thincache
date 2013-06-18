<?php

class CacheApc extends CacheAbstract {
    private static $requestStats = array();
    
    private static $supported = null;
    
    public function __construct() {
        if (empty(self::$requestStats)) {
            self::$requestStats['get'] = 0;
            self::$requestStats['set'] = 0;
            self::$requestStats['del'] = 0;
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see Cache::get()
     */
    public function get($key, $default = null) {
        $key = $this->cacheKey($key);
        
        $success = false;
        
        self::$requestStats['get']++;
        $val = apc_fetch($key, $success);
        if ($success) {
            return $val;
        }
        
        return $default;        
    }
    
    /**
     * (non-PHPdoc)
     * @see Cache::set()
     */
    public function set($key, $value, $expire) {
        $key = $this->cacheKey($key);
        
        self::$requestStats['set']++;
        apc_store($key, $value, $this->calcTtl($expire));
    }
    
    /**
     * (non-PHPdoc)
     * @see Cache::delete()
     */
    public function delete($key) {
        $key = $this->cacheKey($key);
        
        self::$requestStats['del']++;
        apc_delete($key);
    }
    
    public function supported() {
        if (self::$supported === null) {
            self::$supported = extension_loaded('apc') && ini_get('apc.enabled') && class_exists('APCIterator', false);
        }
        
        return self::$supported;
    }
    
    /**
     * clears the whole APC cache globally
     */
    public function clear() {
        apc_clear_cache('user');
    }
    
    public function getStats() {
        $cinfo = apc_cache_info('user', true);
        $apcIt = new APCIterator('user');
        $size = $apcIt->getTotalSize();
        
        $stats = array();
        $stats['size'] = $size;
        $stats['hits'] = $cinfo['num_hits'];
        $stats['misses'] = $cinfo['num_misses'];
        $stats['more']   = 'r/w/d='. self::$requestStats['get'] . '/'.self::$requestStats['set']. '/'.self::$requestStats['del'];
                
        return $stats;
    }
}