<?php

/**
 * Caches nothing, mostly for development environments
 * 
 * @author mstaab
 */
class CacheNothing extends CacheAbstract
{
    /**
     * (non-PHPdoc)
     * @see CacheInterface::get()
     */
    public function get($key, $default = null)
    {
        return $default;
    }

    /**
     * (non-PHPdoc)
     * @see CacheInterface::set()
     */
    public function set($key, $value, $expire)
    {
        // do nothing
    }
    
    /**
     * (non-PHPdoc)
     * @see CacheInterface::delete()
     */
    public function delete($key)
    {
        // do nothing
    }
    
    public function supported() {
        return true;
    }
}
