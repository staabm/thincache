<?php
interface CacheInterface
{
    /**
     * retrieves a item from the cache identified by $key, fallback to $default if nothing can be found.
     * 
     * @param string $key
     * @param mixed $default value to be returned when no cached value found
     * 
     * @return mixed the stored item or $default if nothing can be found
     */
    public function get($key, $default = null);
    
    /**
     * stores a item into the cache for $expire seconds identified by $key
     * 
     * @param string $key
     * @param mixed $value
     * @param int $expire seconds until expires 
     */
    public function set($key, $value, $expire);
    
    /**
     * deletes the item identified by $key
     * 
     * @param string $key
     */
    public function delete($key);
    
    /**
     * tries to find a item identified by $key.
     * If nothing can be found $callable is invoked and afterwards stored for $expire seconds indentfied by $key.
     * 
     * @param string $key
     * @param Callable $callable
     * @param int $expire seconds until expires or a timestamp
     * @return mixed
     */
    public function lazyLookup($key, $callable, $expire);
    
    /**
     * checks whether the given cache impl is supported of the environment/server
     */
    public function supported();
}


class CacheException extends Exception {}