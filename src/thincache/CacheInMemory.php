<?php

/**
 * Cache data in-memory using APC when present and fallback to memcache if not.
 * Use this cache-backend only for small chunks of data, because APC cache has a certain size limit, see php-ini "apc.shm_size".
 *
 * @author mstaab
 */
class CacheInMemory extends CacheAbstract {

    /**
     * @var CacheInterface
     */
    private $backend;

    public function get($key, $default = null) {
        $this->init();

        return $this->backend->get($key, $default);
    }

    public function set($key, $value, $expire) {
        $this->init();

        return $this->backend->set($key, $value, $expire);
    }

    public function delete($key) {
        $this->init();

        return $this->backend->delete($key);
    }

    protected function init() {
        if ($this->backend) return;

        if (PHP_SAPI == 'cli') {
            $supported = array('CacheMemcached', 'CacheMemcache');
        } else {
            $supported = array('CacheApcu', 'CacheApc', 'CacheMemcached', 'CacheMemcache');
        }

        foreach($supported as $backend) {
            /** @var $cache CacheInterface */
            $cache = new $backend();
            if ($cache->supported()) {
                break;
            }
        }

        if (!$cache || !$cache->supported()) {
            throw new InvalidArgumentException('Caching is not supported: Missing either APC/APCu or Memcached or Memcache!');
        }

        $this->backend = new CacheProxy($cache);
    }

    public function supported() {
        return true;
    }
}
