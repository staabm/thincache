<?php

/**
 * Cache data in-memory using APC when present and fallback to memcache if not.
 * Use this cache-backend only for small chunks of data, because APC cache has a certain size limit, see php-ini "apc.shm_size".
 * 
 * @author mstaab
 */
class CacheInMemory extends CacheAbstract {
    
    /**
     * @var CacheInterface
     */
    private $backend;
    
    /**
     * (non-PHPdoc)
     * @see Cache::get()
     */
    public function get($key, $default = null) {
        $this->init();
        
        return $this->backend->get($key, $default);
    }
    
    /**
     * (non-PHPdoc)
     * @see Cache::set()
     */
    public function set($key, $value, $expire) {
        $this->init();
        
        return $this->backend->set($key, $value, $expire);
    }
    
    /**
     * (non-PHPdoc)
     * @see Cache::delete()
     */
    public function delete($key) {
        $this->init();
        
        return $this->backend->delete($key);
    }
    
    protected function init() {
        if ($this->backend) return;
        
        foreach(array('CacheApc', 'CacheMemcached', 'CacheMemcache') as $backend) {
            // require manually because we need this class while setting up autoload'ing
            require_once dirname(__FILE__) ."/$backend.php";
            
            /** @var $cache CacheInterface */
            $cache = new $backend();
            if ($cache->supported()) {
                break;
            }
        }
        
        if (!$cache || !$cache->supported()) {
            throw new IllegalArgumentException('Caching is not supported: Missing either APC or Memcached or Memcache!');
        }
        
        // require manually because we need this class while setting up autoload'ing
        require_once dirname(__FILE__) ."/CacheProxy.php";
        $this->backend = new CacheProxy($cache);
    }
    
    public function supported() {
        return true;
    }
}