<?php

/**
 * Abstract Baseclass for CacheInterface implementing classes
 *  
 * @author mstaab
 */

abstract class CacheAbstract implements CacheInterface {
    /**
     * (non-PHPdoc)
     * @see CacheInterface::lazyLookup()
     */
    public function lazyLookup($key, $callable, $expire) {
        $val = $this->get( $key, 'clx-cache-null' );
    
        if ( $val !== 'clx-cache-null' ) {
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
    protected function calcTtl($expire) {
        if ($expire > 2592000) {
            return $expire - time(); 
        }
        return $expire;
    }
    
    protected function cacheKey($key) {
        return $_SERVER['HTTP_HOST'] . "/" . APP_MODE ."@". ROCKET_REVISION ."/". $key;
    }
}