<?php

/**
 * Persist into Memcached (newer php extension)
 *
 * NOTE: Memcache vs. Memcache_d_
 *
 * @author mstaab
 */
class CacheMemcached extends CacheAbstract
{
    private static $memcache = null;

    private static $requestStats = array();

    private static function connect()
    {
        if (self::$memcache) {
            return;
        }

        if (!defined('MEMCACHE_HOST')) {
            throw new CacheException('Missing required constant MEMCACHE_HOST');
        }
        if (!defined('MEMCACHE_PORT')) {
            throw new CacheException('Missing required constant MEMCACHE_PORT');
        }

        self::$memcache = new Memcached();
        if (! self::$memcache->addServer(MEMCACHE_HOST, MEMCACHE_PORT)) {
            throw new CacheException('Unable to add server ' . MEMCACHE_HOST . ' on port ' . MEMCACHE_PORT . ', ResultCode:' . self::$memcache->getResultCode());
        }

        self::$requestStats['get'] = 0;
        self::$requestStats['set'] = 0;
        self::$requestStats['del'] = 0;
    }

    /**
     *
     * @param string|CacheKey $key
     * @return string
     */
    protected function cacheKey($key)
    {
        $stringKey = parent::cacheKey($key);

        // memcache doesn't like spaces in cache-keys
        return str_replace(' ', '_', $stringKey);
    }

    public function get($key, $default = null)
    {
        $this->connect();

        $key = $this->cacheKey($key);

        self::$requestStats['get'] ++;
        $val = self::$memcache->get($key);

        // support values like 0, array() or null
        if ($val === false && self::$memcache->getResultCode() != Memcached::RES_SUCCESS) {
            return $default;
        }

        return $val;
    }

    public function set($key, $value, $expire)
    {
        $this->connect();

        $key = $this->cacheKey($key);

        self::$requestStats['set'] ++;
        if (self::$memcache->set($key, $value, $this->calcTtl($expire)) === false) {
            throw new CacheException('Unable to set value using key ' . $key . ', ResultCode:' . self::$memcache->getResultCode() . ', Error:' . self::$memcache->getResultMessage());
        }
    }

    /**
     * Atomic add operation.
     *
     * @param string|CacheKey $key
     * @param mixed $value
     * @param int $expire
     *            seconds until expires
     * @return bool
     */
    public function add($key, $value, $expire)
    {
        $this->connect();

        $key = $this->cacheKey($key);

        self::$requestStats['add'] ++;
        return self::$memcache->add($key, $value, $this->calcTtl($expire));
    }

    /**
     * increments a counter
     *
     * @param string|CacheKey $key
     * @param int $step
     * @param int $expire
     *
     * @return int The current value of the counter. Returns 0 when the counter has just been created.
     *
     * @since 0.9.0
     */
    public function increment($key, $step, $expire)
    {
        $this->connect();

        $key = $this->cacheKey($key);

        self::$requestStats['set'] ++;

        $binProtocol = self::$memcache->getOption(Memcached::OPT_BINARY_PROTOCOL);
        // we need the binary protocol to get support for 4 args in increment().
        // ASCII protocoll (default) only supports 2 args (misses default_initial, expiry).
        self::$memcache->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
        $val = self::$memcache->increment($key, $step, 1, $this->calcTtl($expire));
        self::$memcache->setOption(Memcached::OPT_BINARY_PROTOCOL, $binProtocol);

        if (self::$memcache->getResultCode() != Memcached::RES_SUCCESS) {
            throw new CacheException('Unable to increment value using key ' . $key . ', ResultCode:' . self::$memcache->getResultCode() . ', Error:' . self::$memcache->getResultMessage());
        }

        return $val;
    }

    /**
     * Returns all cached entries which key matches the given regexKey
     *
     * @param string|CacheKey $regexKey
     * @param int $limit
     * @return array
     * @phpstan-return list<array{key: CacheKeyStatic, value: mixed}>
     *
     * @since 0.9.0
     */
    public function getRegex($regexKey, $limit = 100)
    {
        $this->connect();

        $regexKey = $this->cacheKey($regexKey);

        $keys = self::$memcache->getAllKeys();
        if (false === $keys) {
            throw new CacheException('Unable to fetch all keys, ResultCode:' . self::$memcache->getResultCode() . ', Error:' . self::$memcache->getResultMessage());
        }

        $keys = preg_grep($regexKey, $keys);
        $matchedKeys = array_slice($keys, 0, $limit);

        if (empty($matchedKeys)) {
            return array();
        }

        $allEntries = self::$memcache->getMulti($matchedKeys);
        if (self::$memcache->getResultCode() !== Memcached::RES_SUCCESS) {
            // getAllKeys() is non-atomic and sometimes returns keys which no longer exists at getMulti() time.
            // in such case retrieve key by key and filter out the erroneous ones.
            if (self::$memcache->getResultCode() === Memcached::RES_NOTFOUND) {
                $allEntries = array();
                foreach ($matchedKeys as $key) {
                    $val = self::$memcache->get($key);

                    if (self::$memcache->getResultCode() === Memcached::RES_SUCCESS) {
                        $allEntries[$key] = $val;
                        self::$requestStats['get'] ++;
                    }
                }
            } else {
                throw new CacheException('Unable to fetch all values, ResultCode:' . self::$memcache->getResultCode() . ', Error:' . self::$memcache->getResultMessage() . print_r($matchedKeys, true) . print_r($allEntries, true));
            }
        } else {
            self::$requestStats['get'] ++;
        }

        $res = array();
        foreach ($allEntries as $key => $val) {
            // wrap keys into static-keys so the caller can pass those apc-keys back into the cache-api,
            // without double-prefixing/namespacing issues
            $res[] = array(
                'key' => new CacheKeyStatic($key),
                'value' => $val
            );
        }

        return $res;
    }

    public function delete($key)
    {
        $this->connect();

        $key = $this->cacheKey($key);

        self::$requestStats['del'] ++;
        if (self::$memcache->delete($key) === false) {
            if (self::$memcache->getResultCode() != Memcached::RES_NOTFOUND) {
                throw new CacheException('Unable to delete value using key ' . $key . ', ResultCode:' . self::$memcache->getResultCode() . ', Error:' . self::$memcache->getResultMessage());
            }
        }
    }

    public function supported()
    {
        return class_exists('Memcached', false);
    }

    /**
     * @return array{size: int|null, hits: int, misses: int, more: string}
     */
    public function getStats()
    {
        $this->connect();
        $stats = array();

        // Memcached returns an array of stats, we just use one server -> use first stats
        $cinfo = self::$memcache->getStats();
        if ($cinfo === false) {
            $cinfo = [];
        }
        $memStats = current($cinfo);

        $stats['hits'] = $memStats['get_hits'];
        $stats['misses'] = $memStats['get_misses'];
        $stats['size'] = $memStats['bytes'];
        $stats['more'] = 'r/w/d=' . self::$requestStats['get'] . '/' . self::$requestStats['set'] . '/' . self::$requestStats['del'];

        return $stats;
    }

    /**
     * Returns a Memcache instance.
     * Will try certain fallbacks to get a working implementation
     *
     * @return CacheInterface
     */
    public static function factory()
    {
        $memcached = new CacheMemcached();
        if ($memcached->supported()) {
            return $memcached;
        }
        throw new Exception('Missing memcached php-extension');
    }
}
