<?php
/**
 * Desc: 主流程
 * User: zhangzekang
 * Date: 2018/12/6
 * Time: 上午12:31
 */

use \Core\Conf;
use \Core\Cache;
use \Core\Log;
use \Define\Consts;
use \Utils\Captcha;
use \Utils\Strategy;
use \Rpc\Official;

class Main
{
    protected $officialObj;

    protected $userConf;

    protected $captchaUtil;

    function __construct()
    {
        $this->officialObj = new Official();
        $this->captchaUtil = new Captcha();
        $this->userConf = Conf::getConf('12306_conf', 'user');
        self::checkUserConf();
    }

    protected function checkUserConf() {
        if (empty($this->userConf['account']['username']) || empty($this->userConf['account']['password'])) {
            Log::error('请先到conf/user.yaml中配置12306帐号及密码');
        }

        if (empty($this->userConf['from_station']) || empty($this->userConf['to_station'])) {
            Log::error('请先到conf/user.yaml中配置出发城市、到达城市');
        }

        if (empty($this->userConf['passengers'][0])) {
            Log::error('请先到conf/user.yaml中配置乘车人姓名');
        }

        if (empty($this->userConf['trip_dates'][0])) {
            Log::error('请先到conf/user.yaml中配置出行时间');
        }

        // 车次为大写
        foreach ($this->userConf['expect_trains'] as &$expectTrain) {
            $expectTrain = strtoupper($expectTrain);
        }
        unset($expectTrain);
    }

    public function start() {

        $cycleTime = 0;
        $startMsg = sprintf(
            "开始抢由【%s】到【%s】的票，日期【%s】，乘车人【%s】，车次【%s】，坐席【%s】",
            $this->userConf['from_station'],
            $this->userConf['to_station'],
            implode('、', $this->userConf['trip_dates']),
            implode('、', $this->userConf['passengers']),
            empty($this->userConf['expect_trains'][0]) ? '全部' : implode('、', $this->userConf['expect_trains']),
            empty($this->userConf['seat_types'][0]) ? '全部' : implode('、', $this->userConf['seat_types'])
        );

        do {
            // 校验是否在可订时间段
            if (!$this->isAvailableTime()) {
                if ($cycleTime === 0) {
                    Log::info('车票可售时间段为 7:00-23:00，系统将 7:00开始运行');
                }
                sleep(60);
                continue;
            }

            // 登陆流程
            $this->proLogin();

            // 首次输出抢票信息
            if ($cycleTime === 0) {
                Log::info($startMsg);
            }

            // 遍历出行时间
            foreach ($this->userConf['trip_dates'] as $tripDate) {

                // 获取可用车次
                $trainsInfo = $this->proQueryTrain($tripDate);

                // 更新查询次数
                $queryTicketMultiCount = Cache::get(Consts::CACHE_QUERY_TICKET_MULTI_COUNT['key']) ?? Strategy::QUERY_TICKET_MULTI_MAX;
                $queryTimes = $queryTicketMultiCount + Cache::get(Consts::CACHE_QUERY_TIMES['key']) ?? 0;
                Cache::set(Consts::CACHE_QUERY_TIMES['key'], $queryTimes, Consts::CACHE_QUERY_TIMES['ttl']);

                // 未查到合适的车次及余票
                if (empty($trainsInfo)) {

                    // 每循环100次提醒
                    if ($cycleTime % 100 === 0) {
                        Log::info("未找到合适车次，已进行【 $queryTimes 】次查询");
                    }

                    continue;
                }

                // 遍历可用车次
                foreach ($trainsInfo as $trainInfo) {

                    // 获取乘车人信息
                    $passengerInfo = $this->proQueryPassenger($trainInfo);

                    // 抢票
                    if ($this->proGetTicket($trainInfo, $passengerInfo)) {
                        exit(0);
                    }
                }
            }

            sleep(Consts::QUERY_TICKET_SLEEP_SECOND);
            $cycleTime ++;
        } while (true);

    }

    protected function isAvailableTime() {
        $currTime = date('H:i', time());
        if ($currTime >= Consts::AVAILABLE_START_TIME && $currTime < Consts::AVAILABLE_END_TIME) {
            return true;
        }
        return false;
    }

    protected function proLogin() {

        // 检查登陆状态
        if ($this->officialObj->checkLogin()) {
            return true;
        }

        do {
            // 获取验证码之前清空cookie，因为第一次没通过校验，后面验证码会变难
            $this->officialObj->cleanCookies();

            // 获取验证码
            $getRes = $this->officialObj->getCaptcha('login');
            if (!$getRes) {
                // 验证码请求限制，10秒后重试
                sleep(10);
                continue;
            }

            // 识别验证码
            $captcha = $this->captchaUtil->recognize();
            if (empty($captcha)) {
                continue;
            }

            // 校验验证码
            $checkRes = $this->officialObj->checkCaptcha($captcha);

        } while (!isset($checkRes) || $checkRes !== true);

        return $this->officialObj->login($this->userConf['account']['username'], $this->userConf['account']['password']);
    }

    protected function proQueryTrain($tripDate) {

        // 查询车次
        $multiCount = Cache::get(Consts::CACHE_QUERY_TICKET_MULTI_COUNT['key']) ?? Strategy::QUERY_TICKET_MULTI_MAX;
        $trains = $this->officialObj->multiQueryTrains($tripDate, $this->userConf['from_station'], $this->userConf['to_station'], $multiCount);

        if (empty($trains)) {
            Strategy::queryTicketMultiCount();
            return null;
        }

        // 格式可用车次
        $availTrains = $this->prepareAvailTrains($trains);

        return $availTrains;
    }

    protected function proQueryPassenger($trainInfo) {

        // 查询乘车人
        $passengers = $this->officialObj->getPassengers();

        // 格式乘车信息
        $passengerInfo = $this->preparePassengerInfo($passengers, $trainInfo);

        return $passengerInfo;
    }

    protected function proGetTicket($trainInfo, $passengerInfo) {

        // 提交订单
        $submitRes = $this->officialObj->submitOrder($trainInfo);
        
        // 获取订单详情
        $orderInfo = $this->officialObj->getOrderInfo();

        // 检查订单
        if (isset($submitRes) && $submitRes) {
            $checkRes = $this->officialObj->checkOrder($passengerInfo, $orderInfo);
        }

        // 获取排队人数
        if (isset($checkRes) && $checkRes) {
            $trainNo = $orderInfo['ticket_info']['queryLeftTicketRequestDTO']['station_train_code'];

            if (!empty($trainNo)) {

                do {
                    $queueCount = $this->officialObj->getQueueCount($passengerInfo, $orderInfo);
                    sleep(1);
                } while ($queueCount != 0);

                // 查询出错，关小黑屋，防止错过正常票
                if ($queueCount === false) {
                    Log::warning($trainNo . ' 车次关小黑屋，时间：' . (Consts::CACHE_BLACK_HOUSE['ttl'] / 60) . '分钟');
                    Cache::set(Consts::CACHE_BLACK_HOUSE['key'] . $trainNo, 1, Consts::CACHE_BLACK_HOUSE['ttl']);
                }
            }
        }

        // 确认订单
        if (isset($queueCount) && $queueCount === 0) {
            $confirmRes = $this->officialObj->confirmOrder($passengerInfo, $orderInfo);
        }

        // 等待完成
        if (isset($confirmRes) && $confirmRes) {
            $currCount = 0;

            // 避免一直等待
            do {
                $waitRes = $this->officialObj->waitOrder();
                if (!$waitRes) {
                    $currCount ++;
                    sleep(1);
                } else {
                    return true;
                }

            } while ($currCount < Consts::ORDER_WAIT_RETRY_COUNT);

            // 等待失败取消未完成订单
            $orderNo = $this->officialObj->getOrderNoComplete();
            if ($orderNo) {
                $this->officialObj->cancelOrderNoComplete($orderNo);
            }
        }

        return false;
    }

    protected function prepareAvailTrains($trains) {
        $seatTypes = $this->userConf['seat_types'];
        $trainTypes = $this->userConf['train_types'];
        $expectTrains = $this->userConf['expect_trains'];
        $passengers = $this->userConf['passengers'];
        $fromName = $this->userConf['from_station'];
        $toName = $this->userConf['to_station'];
        $trainArr = [];

        // 格式化车次
        foreach ($trains as $trainInfo) {

            // format("secretStr|预订|24000C259703|C2597|VNP|YKP|VNP|TJP|18:03|18:40|00:37|N|secretStr2|20181204|3|P3|01|03|1|0|||||||无||||无|无|无||O090M0O0|O9MO|0")
            $info = explode('|', $trainInfo);
            $trainNo = $info[3];

            // 期望的车次
            if (!empty($expectTrains[0])) {
                if (!in_array($trainNo, $expectTrains)) {
                    continue;
                }
            }

            // 期望的列车类型
            if (!empty($trainTypes[0])) {
                $trainType = substr($trainNo, 0, 1);
                $trainType = ($trainType == 'G' || $trainType == 'D') ? $trainType : 'O';

                if (!in_array($trainType, $trainTypes)) {
                    continue;
                }
            }

            // 期望的坐席
            if (empty($seatTypes[0])) {
                $seatTypes = Consts::SEAT_ALL;
            }

            // 是否加入小黑屋
            if (Cache::get(Consts::CACHE_BLACK_HOUSE['key'] . $trainNo) !== null) {
                continue;
            }

            // 可预定
            if ($info[11] == 'Y') {

                // 期望的座位类型
                foreach ($seatTypes as $seatItem) {

                    // 坐席配置有误
                    if (!isset(Consts::SEAT_TYPE_KEY[$seatItem])) {
                        Log::error('配置有误：坐席名称输入有误 ' . $seatItem);
                    }

                    $seatCode = Consts::SEAT_TYPE_KEY[$seatItem];
                    $seatStatus = $info[$seatCode];

                    // 有票
                    if (!empty($seatStatus) && $seatStatus != '无' && $seatStatus != '*') {

                        $train = [
                            'secret_str' => urldecode($info[0]),
                            'train_id' => $info[2],
                            'train_no' => $info[3],
                            'from_no' => $info[6],
                            'to_no' => $info[7],
                            'start_time' => $info[8],
                            'arrive_time' => $info[9],
                            'take_time' => $info[10],
                            'left_ticket' => $info[12],
                            'trip_date' => substr($info[13], 0, 4) . '-' . substr($info[13], 4, 2) . '-' . substr($info[13], 6, 2),
                            'train_location' => $info[15],
                            'ticket_num' => is_int($seatStatus) ? $seatStatus : -1, // -1代表有票，但不知剩余票数
                            'seat_type' => $seatItem,
                            'seat_code' => $seatCode,
                            'from_name' => $fromName,
                            'to_name' => $toName,
                        ];

                        // 乘车人数过多
                        if ($train['ticket_num'] != '-1' && count($passengers) > $train['ticket_num']) {
                            Log::info(sprintf('余票数量小于乘车人数，余票：[%d]张；人数：[%d]', $train['ticket_num'], count($passengers)));
                            continue;
                        }

                    }
                }
            }

            if (!empty($train)) {
                $trainArr[] = $train;
                unset($train);
            }
        }
        return $trainArr;
    }

    protected function preparePassengerInfo($passengers, $trainInfo) {
        $passengerNameArr = $this->userConf['passengers'];
        $seatType = $trainInfo['seat_type'];
        $seatCode = Consts::SEAT_TYPE_CODE[$seatType];
        $passengersByName = [];
        $passengerArr = [];
        $passengerTicketStr = '';
        $oldPassengerTicketStr = '';

        // 未指定乘车人
        if (empty($passengerNameArr)) {
            return null;
        }

        // 按姓名分组
        foreach ($passengers as $passenger) {
            $passengersByName[$passenger['passenger_name']] = $passenger;
        }

        // 获取指定乘车人信息
        foreach ($passengerNameArr as $passengerName) {

            // 乘车人是否存在
            if (!isset($passengersByName[$passengerName])) {
                Log::error('配置有误：该账户下未找到乘车人 ' . $passengerName);
            }

            $passengerArr[] = $passengersByName[$passengerName];
        }

        // 格式化乘车人信息
        foreach ($passengerArr as $passengerInfo) {

            // format = 1,0,1,张三,1,11320219960813xxxx,1560318xxxx,N
            $passengerTicketStr .=
                $seatCode . ',0,' .
                $passengerInfo['passenger_type'] . ',' .
                $passengerInfo['passenger_name'] . ',' .
                $passengerInfo['passenger_id_type_code'] . ',' .
                $passengerInfo['passenger_id_no'] . ',' .
                $passengerInfo['mobile_no'] . ',N_';

            // format = 张三,1,11320219960813xxxx,1_
            $oldPassengerTicketStr .=
                $passengerInfo['passenger_name'] . ',' .
                $passengerInfo['passenger_id_type_code'] . ',' .
                $passengerInfo['passenger_id_no'] . ',' .
                $passengerInfo['passenger_type'] . '_';
        }

        //移除末尾下划线
        $passengerTicketStr = mb_substr($passengerTicketStr, 0, -1);
        $oldPassengerTicketStr = mb_substr($oldPassengerTicketStr, 0, -1);

        return [
            'passenger_ticket_str' => $passengerTicketStr,
            'old_passenger_str' => $oldPassengerTicketStr,
            'seat_code' => $seatCode,
        ];
    }

}

return new Main();