<?php

class CacheProxy extends CacheAbstract
{
    /**
     * @var CacheInterface
     */
    private $proxyStore;
    
    
    /**
     * @var int
     */
    private $proxyExpireTime;
    
    /**
     * @var CacheInterface
     */
    private $backend;
    
    public function __construct(CacheInterface $backend, $proxyStore = null, $proxyExpiretime = 3600) {
        // require manually because we need this class while setting up autoload'ing
        require_once dirname(__FILE__) ."/CacheArray.php";
        
        $this->backend = $backend;
        $this->proxyStore = $proxyStore ?: new CacheArray();
        $this->proxyExpireTime = $proxyExpiretime;
    }
    
    /**
     * (non-PHPdoc)
     * @see CacheInterface::get()
     */
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
    
    /**
     * (non-PHPdoc)
     * @see CacheInterface::set()
     */
    public function set($key, $value, $expire)
    {
        $this->proxyStore->set($key, $value, $expire);
        $this->backend->set($key, $value, $expire);
    }
    
    /**
     * (non-PHPdoc)
     * @see CacheInterface::delete()
     */
    public function delete($key)
    {
        $this->proxyStore->delete($key);
        $this->backend->delete($key);
    }
    
    public function supported() {
        return $this->backend->supported();
    }   
}
