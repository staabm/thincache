<?php

class CacheChain extends CacheAbstract {
    /**
     * @var CacheInterface[]
     */
    private $chain = array();
    
    public function get($key, $default = null) {
        
        foreach($this->chain as $cache) {
            $cachedVal = $cache->get($key, $default);
            
            if ($cachedVal !== $default) {
                return $cachedVal;
            }
        }
        
        return $default;
    }
    
    public function set($key, $value, $expire) {
        foreach($this->chain as $cache) {
            $cache->set($key, $value, $expire);
        }
    }
    
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