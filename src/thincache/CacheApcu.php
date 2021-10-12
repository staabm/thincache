<?php

class CacheApcu extends CacheAbstract
{
    private static $requestStats = array();

    private static $supported = null;

    public function __construct()
    {
        if (empty(self::$requestStats)) {
            self::$requestStats['get'] = 0;
            self::$requestStats['set'] = 0;
            self::$requestStats['del'] = 0;
        }
    }

    public function get($key, $default = null)
    {
        $key = $this->cacheKey($key);
        
        $success = false;
        
        self::$requestStats['get'] ++;
        $val = apcu_fetch($key, $success);
        if ($success) {
            return $val;
        }
        
        return $default;
    }

    public function set($key, $value, $expire)
    {
        $key = $this->cacheKey($key);
        
        self::$requestStats['set'] ++;
        apcu_store($key, $value, $this->calcTtl($expire));
    }

    public function delete($key)
    {
        $key = $this->cacheKey($key);
        
        self::$requestStats['del'] ++;
        apcu_delete($key);
    }

    public function supported()
    {
        if (self::$supported === null) {
            $supported = extension_loaded('apcu') && class_exists('APCUIterator', false);
            
            if (PHP_SAPI === 'cli') {
                self::$supported = $supported && ini_get('apc.enable_cli');
            } else {
                self::$supported = $supported && ini_get('apc.enabled');
            }
        }
        
        return self::$supported;
    }

    /**
     * increments a counter
     *
     * @param string|CacheKey $key
     * @param int $step
     * @param int $expire
     *
     * @return false|int False on error, otherwise the current value of the counter. Returns 0 when the counter has been created.
     */
    public function increment($key, $step, $expire)
    {
        $key = $this->cacheKey($key);
        
        // try to increment a already existing counter
        $val = apcu_inc($key, $step, $success);
        self::$requestStats['set'] ++;
        
        // counter seems not to be existing
        if (! $success) {
            // init the counter using add() to circumvent race conditions
            apcu_add($key, 0, $this->calcTtl($expire));
            self::$requestStats['set'] ++;
            
            // increment again after counter creation
            $val = apcu_inc($key, $step, $success);
            self::$requestStats['set'] ++;
        }
        
        if ($success) {
            return $val;
        }
        return false;
    }

    /**
     * Returns all cached entries which key matches the given regexKey
     *
     * @param string|CacheKey $regexKey
     * @param int $limit
     * @return array
     * @phpstan-return list<array{key: CacheKeyStatic, value: mixed}>
     */
    public function getRegex($regexKey, $limit = 100)
    {
        $regexKey = $this->cacheKey($regexKey);
        
        $it = new APCuIterator($regexKey, APC_ITER_KEY|APC_ITER_VALUE, $limit, APC_LIST_ACTIVE);
        self::$requestStats['get'] ++;
        
        $res = array();
        foreach ($it as $k => $v) {
            // wrap keys into static-keys so the caller can pass those apc-keys back into the cache-api,
            // without double-prefixing/namespacing issues
            $v['key'] = new CacheKeyStatic($v['key']);
            $res[] = $v;
        }
        
        return $res;
    }

    public function clearRegex($regexKey, $expiredOnly = false)
    {
        $regexKey = $this->cacheKey($regexKey);
        
        $it = new APCuIterator($regexKey);
        
        $now = time();
        foreach ($it as $apcKey => $item) {
            if (! $expiredOnly || $expiredOnly && ($item['creation_time'] + $item['ttl']) < $now) {
                // we need to call the apc-api directly, because apcKey is absolute
                self::$requestStats['del'] ++;
                apcu_delete($apcKey);
            }
        }
    }

    /**
     * clears the whole cache globally
     */
    public function clear()
    {
        apcu_clear_cache();
    }

    /**
     * @return array{size: int|null, hits: int, misses: int, more: string}
     */
    public function getStats()
    {
        $cinfo = apcu_cache_info(true);
        
        // support apc and old versions of apcu
        $hits = $misses = 0;
        
        if (! empty($cinfo['num_hits'])) {
            $hits = $cinfo['num_hits'];
        } elseif (! empty($cinfo['nhits'])) {
            $hits = $cinfo['nhits'];
        }
        if (! empty($cinfo['num_misses'])) {
            $misses = $cinfo['num_misses'];
        } elseif (! empty($cinfo['nmisses'])) {
            $misses = $cinfo['nmisses'];
        }
        
        $stats = array();
        $stats['size'] = null;
        $stats['hits'] = $hits;
        $stats['misses'] = $misses;
        $stats['more'] = 'r/w/d=' . self::$requestStats['get'] . '/' . self::$requestStats['set'] . '/' . self::$requestStats['del'];
        
        return $stats;
    }
}
