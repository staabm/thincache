<?php

class CacheProxy extends CacheAbstract
{

    /**
     *
     * @var CacheInterface
     */
    private $proxyStore;

    /**
     *
     * @var int
     */
    private $proxyExpireTime;

    /**
     *
     * @var CacheInterface
     */
    private $backend;

    public function __construct(CacheInterface $backend, CacheInterface $proxyStore = null, $proxyExpiretime = 3600)
    {
        $this->backend = $backend;
        $this->proxyStore = $proxyStore ? $proxyStore : new CacheArray();
        $this->proxyExpireTime = $proxyExpiretime;
    }

    public function get($key, $default = null)
    {
        // lookup proxyStore for fast exit
        $val = $this->proxyStore->get($key, 'clx-cache-null');
        
        if ($val !== 'clx-cache-null') {
            return $val;
        }
        
        // no result in the proxyStore -> retrieve value from backend and
        // store in the proxyStore to serve a possible second call from the proxyStore
        $val = $this->backend->get($key, $default);
        $this->proxyStore->set($key, $val, $this->proxyExpireTime);
        
        return $val;
    }

    public function set($key, $value, $expire)
    {
        $this->proxyStore->set($key, $value, $expire);
        $this->backend->set($key, $value, $expire);
    }

    public function delete($key)
    {
        $this->proxyStore->delete($key);
        $this->backend->delete($key);
    }

    public function supported()
    {
        return $this->backend->supported();
    }
}
