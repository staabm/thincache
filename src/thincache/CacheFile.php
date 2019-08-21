<?php

/**
 * Per Application file cache.
 *
 * Most of the code taken from symfony1
 *
 * @author mstaab
 */
class CacheFile extends CacheAbstract
{
    const READ_DATA = 1;

    const READ_TIMEOUT = 2;

    const READ_LAST_MODIFIED = 4;

    public function get($key, $default = null)
    {
        $key = $this->cacheKey($key);

        $file_path = $this->getFilePath($key);
        if (! file_exists($file_path)) {
            return $default;
        }

        $data = $this->read($file_path, self::READ_DATA);

        if ($data[self::READ_DATA] === null) {
            return $default;
        }

        return $data[self::READ_DATA];
    }

    public function set($key, $value, $expire)
    {
        $key = $this->cacheKey($key);

        return $this->write($this->getFilePath($key), $value, time() + $this->calcTtl($expire));
    }

    public function delete($key)
    {
        $key = $this->cacheKey($key);
        $path = $this->getFilePath($key);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    protected function getFilePath($key)
    {
        if (!defined('APP_TMP_DIR')) {
            throw new Exception('Missing constant APP_TMP_DIR');
        }
        return APP_TMP_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . md5($key) . '.' . __CLASS__;
    }

    protected function isValid($path)
    {
        $data = $this->read($path, self::READ_TIMEOUT);
        return time() < $data[self::READ_TIMEOUT];
    }

    /**
     * Reads the cache file and returns the content.
     *
     * @param string $path
     *            The file path
     * @param mixed $type
     *            The type of data you want to be returned
     *            CacheFile::READ_DATA: The cache content
     *            CacheFile::READ_TIMEOUT: The timeout
     *            CacheFile::READ_LAST_MODIFIED: The last modification
     *            timestamp
     *
     * @return array the (meta)data of the cache file. E.g.
     *         $data[CacheFile::READ_DATA]
     *
     * @throws CacheException
     */
    protected function read($path, $type = self::READ_DATA)
    {
        if (! $fp = @fopen($path, 'rb')) {
            throw new CacheException(sprintf('Unable to read cache file "%s".', $path));
        }

        @flock($fp, LOCK_SH);
        $data[self::READ_TIMEOUT] = intval(@stream_get_contents($fp, 12, 0));
        if ($type != self::READ_TIMEOUT && time() < $data[self::READ_TIMEOUT]) {
            if ($type & self::READ_LAST_MODIFIED) {
                $data[self::READ_LAST_MODIFIED] = intval(@stream_get_contents($fp, 12, 12));
            }
            if ($type & self::READ_DATA) {
                fseek($fp, 0, SEEK_END);
                $length = ftell($fp) - 24;
                fseek($fp, 24);
                $data[self::READ_DATA] = @fread($fp, $length);
            }
        } else {
            $data[self::READ_LAST_MODIFIED] = null;
            $data[self::READ_DATA] = null;
        }
        @flock($fp, LOCK_UN);
        @fclose($fp);

        return $data;
    }

    /**
     * Writes the given data in the cache file.
     *
     * @param string $path
     *            The file path
     * @param string $data
     *            The data to put in cache
     * @param integer $timeout
     *            The timeout timestamp
     *
     * @return boolean true if ok, otherwise false
     *
     * @throws CacheException
     */
    protected function write($path, $data, $timeout)
    {
        $current_umask = umask();
        umask(0000);

        if (! is_dir(dirname($path))) {
            // create directory structure if needed
            mkdir(dirname($path), 0777, true);
        }

        $tmpFile = tempnam(dirname($path), basename($path));

        if (! $fp = @fopen($tmpFile, 'wb')) {
            throw new CacheException(sprintf('Unable to write cache file "%s".', $tmpFile));
        }

        @fwrite($fp, str_pad($timeout, 12, 0, STR_PAD_LEFT));
        @fwrite($fp, str_pad(time(), 12, 0, STR_PAD_LEFT));
        @fwrite($fp, $data);
        @fclose($fp);

        // Hack from Agavi (http://trac.agavi.org/changeset/3979)
        // With php < 5.2.6 on win32, renaming to an already existing file
        // doesn't work, but copy does,
        // so we simply assume that when rename() fails that we are on win32 and
        // try to use copy()
        if (! @rename($tmpFile, $path)) {
            if (copy($tmpFile, $path)) {
                unlink($tmpFile);
            }
        }

        chmod($path, 0666);
        umask($current_umask);

        return true;
    }

    public function supported()
    {
        return true;
    }
}
