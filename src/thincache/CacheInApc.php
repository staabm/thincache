<?php

/**
 * Cache data in-memory using APCu when present and fallback to APC if not.
 * Use this cache-backend only for small chunks of data, because APC cache has a certain size limit, see php-ini "apc.shm_size".
 *
 * @author mstaab
 * @since 0.8.0
 */
class CacheInApc extends CacheAbstract {

    /**
     * @var CacheApcu|CacheApc|null
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

    /**
     * Returns all cached entries which key matches the given regexKey
     *
     * @param string $regexKey
     * @param int $limit
     * @return array
     *
     * @see CacheApcu#getRegex(), CacheApc#getRegex()
     */
    public function getRegex($regexKey, $limit = 100) {
        $this->init();

        return $this->backend->getRegex($regexKey, $limit);
    }

    /**
     * APC* specific APIs
     *
     * @see CacheApcu#clear(), CacheApc#clear()
     */
    public function clear() {
        $this->init();

        return $this->backend->clear();
    }

    /**
     * APC* specific APIs
     *
     * @see CacheApcu#increment(), CacheApc#increment()
     */
    public function increment($key, $step = 1, $expire) {
        $this->init();

        return $this->backend->increment($key, $step, $expire);
    }

    /**
     * APC* specific APIs
     *
     * @see CacheApcu#decrement(), CacheApc#decrement()
     */
    public function decrement($key, $step = 1, $expire) {
        $this->init();

        return $this->backend->decrement($key, $step, $expire);
    }

    protected function init() {
        if ($this->backend) return;

        $supported = array('CacheApcu', 'CacheApc');

        $cache = null;
        foreach($supported as $backend) {
            /** @var $cache CacheInterface */
            $cache = new $backend();
            if ($cache->supported()) {
                break;
            }
        }

        // as of now we cannot use CacheProxy because of the greater interface of APC* required
        $this->backend = $cache;
    }

    public function supported() {
        $this->init();

        return (bool) $this->backend;
    }
}
