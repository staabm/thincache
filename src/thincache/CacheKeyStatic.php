<?php

class CacheKeyStatic implements CacheKey {
    private $key;
    
    /**
     * @param string $key
     */
    public function __construct($key) {
        $this->key = $key;
    }
    
    public function toKey() {
        return $this->key;
    }
}