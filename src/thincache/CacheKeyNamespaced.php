<?php

class CacheKeyNamespaced implements CacheKey {
    /**
     * Global Prefix for all cache-keys to prevent naming collisions between e.g. apps, frameworks, libs.. 
     * 
     * @var string
     */
    public static $namespace = '/';
    
    /**
     * @var string
     */
    private $key;
    
    public function __construct($key) {
        $this->key = $key;
    }
    
    public function toKey() {
        return self::$namespace . $this->key;        
    }
}