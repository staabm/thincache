<?php

/**
 * Caches nothing, mostly for development environments
 *
 * @author mstaab
 */
class CacheNothing extends CacheAbstract
{
    public function get($key, $default = null)
    {
        return $default;
    }

    public function set($key, $value, $expire)
    {
        // do nothing
    }
    
    public function delete($key)
    {
        // do nothing
    }
    
    public function supported() {
        return true;
    }
}
