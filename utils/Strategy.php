<?php
/**
 * Desc: 策略
 * User: zhangzekang
 * Date: 2018/12/14
 * Time: 下午4:06
 */

namespace Utils;

use Core\Cache;
use Core\Log;
use Define\Consts;

class Strategy
{
    // 查询余票批量次数最小值
    const QUERY_TICKET_MULTI_MIN = 1;

    // 查询余票批量次数最大值
    const QUERY_TICKET_MULTI_MAX = 100;

    // 查询余票失败等待时间
    const QUERY_TICKET_FAILED_SECOND = 10;

    public static function queryTicketMultiCount() {
        $ipLockTimes = Cache::get(Consts::CACHE_IP_LOCK_TIMES['key']) ?? 0;
        $multiCount = Cache::get(Consts::CACHE_QUERY_TICKET_MULTI_COUNT['key']) ?? self::QUERY_TICKET_MULTI_MAX;

        $ipLockTimes ++;
        $multiCount = intval($multiCount / ($ipLockTimes + 1));
        $multiCount = ($multiCount > self::QUERY_TICKET_MULTI_MIN) ? $multiCount : self::QUERY_TICKET_MULTI_MIN;

        Cache::set(Consts::CACHE_QUERY_TICKET_MULTI_COUNT['key'], $multiCount, Consts::CACHE_QUERY_TICKET_MULTI_COUNT['ttl']);
        Cache::set(Consts::CACHE_IP_LOCK_TIMES['key'], $ipLockTimes, Consts::CACHE_IP_LOCK_TIMES['ttl']);

        sleep($ipLockTimes * self::QUERY_TICKET_FAILED_SECOND);
        Log::info("设置批量查询次数：$multiCount ；等待秒数：".$ipLockTimes * self::QUERY_TICKET_FAILED_SECOND);
    }
}