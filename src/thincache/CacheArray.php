<?php

/**
 * Very simple Array backed Cache implementation,
 * which is mostly usefull to prevent multiple calls to the cache-backend with the same key.
 *
 * This Cache backed does not prefix your cache-keys to prevent name collisions,
 * because it cannot collide with concurrent request as no resources are shared.
 *
 * Be carefull when using this cache, because it prevents objects from beeing garbage collected!
 *
 * Note: The backing store is not persisted between requests!
 *
 * @author mstaab
 */
class CacheArray extends CacheAbstract
{

    private static $NULL = 'clx-cache-null-marker';

    /**
     *
     * @var array
     */
    private $store = array();

    public function get($key, $default = null)
    {
        // we optimize for performance, therefore use isset() and not array_key_exists().
        if (isset($this->store[$key])) {
            $val = $this->store[$key];
            if ($val === self::$NULL) {
                return null;
            }
            return $val;
        }
        return $default;
    }

    public function set($key, $value, $expire)
    {
        // we use isset() in #get() therefore we need a magic marker for NULLs
        if ($value === null) {
            $value = self::$NULL;
        }
        $this->store[$key] = $value;
    }

    public function delete($key)
    {
        unset($this->store[$key]);
    }

    public function supported()
    {
        return true;
    }
}
