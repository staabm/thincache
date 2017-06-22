<?php

/**
 * @deprecated use CacheApcu instead
 */
class CacheApc extends CacheAbstract {
    private static $requestStats = array();

    private static $supported = null;

    public function __construct() {
        if (empty(self::$requestStats)) {
            self::$requestStats['get'] = 0;
            self::$requestStats['set'] = 0;
            self::$requestStats['del'] = 0;
        }
    }

    /**
     * (non-PHPdoc)
     * @see Cache::get()
     */
    public function get($key, $default = null) {
        $key = $this->cacheKey($key);

        $success = false;

        self::$requestStats['get']++;
        $val = apc_fetch($key, $success);
        if ($success) {
            return $val;
        }

        return $default;
    }

    /**
     * (non-PHPdoc)
     * @see Cache::set()
     */
    public function set($key, $value, $expire) {
        $key = $this->cacheKey($key);

        self::$requestStats['set']++;
        apc_store($key, $value, $this->calcTtl($expire));
    }

    /**
     * (non-PHPdoc)
     * @see Cache::delete()
     */
    public function delete($key) {
        $key = $this->cacheKey($key);

        self::$requestStats['del']++;
        apc_delete($key);
    }

    public function supported() {
        if (self::$supported === null) {
            // on ubuntu16 we got zend heap corruptions with APCIterator
            // disable this code path (and the feature until php-src fixed this issue)
            if (PHP_VERSION_ID >= 70000) {
                self::$supported = false;
            } else {
                $supported = extension_loaded('apc') && class_exists('APCIterator', false);

                if (PHP_SAPI === 'cli') {
                    self::$supported = $supported && ini_get('apc.enable_cli');
                } else {
                    self::$supported = $supported && ini_get('apc.enabled');
                }
            }
        }

        return self::$supported;
    }

    /**
     * increments a APC counter
     */
    public function increment($key, $step = 1, $expire) {
        $key = $this->cacheKey($key);

        // try to increment a already existing counter
        $val = apc_inc($key, $step, $success);
        self::$requestStats['set']++;

        // counter seems not to be existing
        if (!$success) {
            // init the counter using add() to circumvent race conditions
            apc_add($key, 0, $this->calcTtl($expire));
            self::$requestStats['set']++;

            // increment again after counter creation
            $val = apc_inc($key, $step, $success);
            self::$requestStats['set']++;
        }

        if ($success) {
            return $val;
        }
        return false;
    }

    public function getRegex($regexKey, $limit = 100) {
        $regexKey = $this->cacheKey($regexKey);

        $it = new APCIterator('user', $regexKey, APC_ITER_ALL, $limit, APC_LIST_ACTIVE);
        self::$requestStats['get']++;

        $res = array();
        foreach($it as $k => $v) {
            // wrap keys into static-keys so the caller can pass those apc-keys back into the cache-api,
            // without double-prefixing/namespacing issues
            $v['key'] = new CacheKeyStatic($v['key']);
            $res[] = $v;
        }

        return $res;
    }

    public function clearRegex($regexKey, $expiredOnly = false) {
        $regexKey = $this->cacheKey($regexKey);

        $it = new APCIterator('user', $regexKey);

        $now = time();
        foreach($it as $apcKey => $item) {
            if (!$expiredOnly || $expiredOnly && ($item['creation_time'] + $item['ttl']) < $now) {
                // we need to call the apc-api directly, because apcKey is absolute
                self::$requestStats['del']++;
                apc_delete($apcKey);
            }
        }
    }

    /**
     * clears the whole APC cache globally
     */
    public function clear() {
        apc_clear_cache('user');
    }

    public function getStats() {
        $cinfo = apc_cache_info('user', true);

        // support apc and old versions of apcu
        $hits = $misses = 0;

        if (!empty($cinfo['num_hits'])) {
            $hits = $cinfo['num_hits'];
        } else if (!empty($cinfo['nhits'])) {
            $hits = $cinfo['nhits'];
        }
        if (!empty($cinfo['num_misses'])) {
            $misses = $cinfo['num_misses'];
        } else if (!empty($cinfo['nmisses'])) {
            $misses = $cinfo['nmisses'];
        }

        $stats = array();
        $stats['size'] = null;
        $stats['hits'] = $hits;
        $stats['misses'] = $misses;
        $stats['more']   = 'r/w/d='. self::$requestStats['get'] . '/'.self::$requestStats['set']. '/'.self::$requestStats['del'];

        return $stats;
    }
}
