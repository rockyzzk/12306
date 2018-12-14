<?php
/**
 * Desc: 缓存常量
 * User: zhangzekang
 * Date: 2018/12/5
 * Time: 下午7:34
 */

namespace Define;

class Consts
{
    const CACHE_LOGIN = [
        'key' => 'is_login',
        'ttl' => 60 * 2,
    ];
    const CACHE_PASSENGER = [
        'key' => 'passenger_info',
        'ttl' => 0,
    ];
    const CACHE_QUERY_TIMES = [
        'key' => 'query_times',
        'ttl' => 0,
    ];
    const CACHE_BLACK_HOUSE = [
        'key' => 'black_house_train_no:', // + 车次
        'ttl' => 60 * 5,
    ];

    const CAPTCHA_POSITION = [
        [41, 46],
        [112, 47],
        [193, 48],
        [264, 49],
        [42, 115],
        [113, 116],
        [194, 117],
        [265, 118],
    ];

    const SEAT_ALL = [
        '二等座',
        '一等座',
        '特等座',
        '商务座',
        '硬座',
        '硬卧',
        '软卧',
        '无座',
    ];

    const SEAT_TYPE_KEY = [
        '商务座' => 32,
        '一等座' => 31,
        '二等座' => 30,
        '特等座' => 25,
        '软卧' => 23,
        '硬卧' => 28,
        '硬座' => 29,
        '无座' => 26,
    ];

    const SEAT_TYPE_CODE = [
        '一等座' => 'M',
        '特等座' => 'P',
        '二等座' => 'O',
        '商务座' => '9',
        '硬座' => '1',
        '无座' => '1',
        '软卧' => '4',
        '硬卧' => '3',
    ];

    // 等待订单重试次数
    const ORDER_WAIT_RETRY_COUNT = 30;

    // 查询余票时间间隔
    const QUERY_TICKET_SLEEP_SECOND = 0;

    // 查询余票失败等待时间
    const QUERY_TICKET_FAILED_SECOND = 10;

    // RPC请求失败等待时间
    const RPC_FAILED_SECOND = 60;

    // 查询余票批量次数
    const QUERY_TICKET_MULTI_COUNT = 10;

    // 查询余票提醒间隔
    const QUERY_TICKET_NOTICE_COUNT = 10000;

    // 可预定时间
    const AVAILABLE_START_TIME = '07:00';
    const AVAILABLE_END_TIME = '23:00';
}