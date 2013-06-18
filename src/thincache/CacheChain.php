<?php

class CacheChain extends CacheAbstract {
    /**
     * @var CacheInterface[]
     */
    private $chain = array();
    
    /**
     * (non-PHPdoc)
     * @see Cache::get()
     */
    public function get($key, $default = null) {
        
        foreach($this->chain as $cache) {
            $cachedVal = $cache->get($key, $default);
            
            if ($cachedVal !== $default) {
                return $cachedVal;
            }
        }
        
        return $default;
    }
    
    /**
     * (non-PHPdoc)
     * @see Cache::set()
     */
    public function set($key, $value, $expire) {
        foreach($this->chain as $cache) {
            $cache->set($key, $value, $expire);
        }
    }
    
    /**
     * (non-PHPdoc)
     * @see Cache::delete()
     */
    public function delete($key) {
        foreach($this->chain as $cache) {
            $cache->delete($key);
        }
    }
    
    public function addCache(CacheInterface $cacheImpl) {
        $this->chain[] = $cacheImpl;
    }
    
    public function supported() {
        return true;
    }
}