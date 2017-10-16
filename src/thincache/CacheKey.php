<?php

interface CacheKey
{

    /**
     *
     * @return string A string representation used as an identifier within a Cache
     */
    public function toKey();
}
