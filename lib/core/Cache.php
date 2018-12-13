<?php
/**
 * Desc: 缓存核心
 * User: zhangzekang
 * Date: 2018/12/4
 * Time: 下午1:30
 */

namespace Core;

use \Doctrine\Common\Cache\ArrayCache;

class Cache extends ArrayCache
{
    protected static $cacheObj;

    protected static function getCacheObj()
    {
        if (empty(self::$cacheObj)) {
            self::$cacheObj = new Cache();
        }
        return self::$cacheObj;
    }

    /**
     * @param $key
     * @param $val
     * @param $lifeTime  (过期时间，单位秒)
     * @return bool
     */
    public static function set($key, $val, $lifeTime = 0) {
        $cache = self::getCacheObj();
        return $cache->doSave($key, $val, $lifeTime);
    }

    public static function get($key) {
        $cache = self::getCacheObj();
        return $cache->doFetch($key);
    }
}