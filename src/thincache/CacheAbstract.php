<?php

/**
 * Abstract Baseclass for CacheInterface implementing classes
 *
 * @author mstaab
 */
abstract class CacheAbstract implements CacheInterface
{
    public function lazyLookup($key, $callable, $expire)
    {
        $val = $this->get($key, 'clx-cache-null');
        
        if ($val !== 'clx-cache-null') {
            return $val;
        }
        
        $val = call_user_func($callable);
        $this->set($key, $val, $expire);
        
        return $val;
    }

    /**
     * Calculate from the given timestamp/ttl the remaining ttl.
     *
     * @param int $expire
     * @return int
     */
    protected function calcTtl($expire)
    {
        if ($expire > 2592000) {
            return $expire - time();
        }
        return $expire;
    }

    /**
     *
     * @param string|CacheKey $key
     * @return string
     */
    protected function cacheKey($key)
    {
        if (! $key instanceof CacheKey) {
            $key = new CacheKeyNamespaced($key);
        }
        return $key->toKey();
    }
}
