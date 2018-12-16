<?php
/**
 * Desc: 12306官方操作
 * User: zhangzekang
 * Date: 2018/11/22
 * Time: 下午4:44
 */

namespace Rpc;

use \Base\RpcBase;
use \Core\Conf;
use \Core\Cache;
use \Core\Log;
use \Define\Consts;
use \Utils\Mail;

class Official extends RpcBase {

    protected $urls;

    protected $appId;

    protected $defaultHeaders = array();

    protected $captchaPath;

    protected $logExceptRequestKeywords = [
        'pictureup'
    ];

    protected $logExceptResponseKeywords = [
        'captcha-image',
        'pictureup',
        'query'
    ];

    function __construct()
    {
        // rpc配置
        $conf = Conf::getConf(null, 'official');
        $this->urls = $conf['urls'];
        $this->appId = $conf['appid'];

        // 验证码路径
        $this->captchaPath = ROOT_PATH . '/' . Conf::getConf('captcha_path');

        $this->initHeaders($this->urls['host']);
        $this->host = $this->urls['protocol'] . '://' . $this->urls['host'];
    }

    protected function initHeaders($host) {
        $this->defaultHeaders = [
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.102 Safari/537.36',
            'Host' => $host
        ];
    }

    protected function __checkResponse($res) {
        $res = json_decode($res, true);
        return $res;
    }

    protected function __send($urlKey, $params = null, $header = null, $multiCount = 0) {

        $headers = array_merge($this->defaultHeaders, [
            'Referer' => $this->urls[$urlKey]['referer'],
        ]);

        if (!empty($header) && is_array($header)) {
            $headers = array_merge($headers, $header);
        }

        switch ($this->urls[$urlKey]['method']) {
            case 'get': $method = '_get'; break;
            case 'post': $method = '_post'; break;
            default : $method = '_get';
        }

        $res = $this->$method($this->urls[$urlKey]['path'], $params, $headers, $multiCount);
        return $res;
    }

    public function getCaptcha($type) {

        switch ($type) {
            case 'login' :
                $urlKey = 'get_captcha_login';
                break;
            case 'order' :
                $urlKey = '';
                break;
            default:
                $urlKey = '';
        }

        $captchaBody = $this->__send($urlKey);

        if (mb_strpos($captchaBody, 'error') !== false) {
            Log::warning('获取验证码失败，10秒后重试');
            return false;
        }

        // 保存到本地
        $res = file_put_contents($this->captchaPath, $captchaBody);

        // 校验结果
        if ($res === false) {
            Log::error('保存验证码失败');
        } else {
            Log::info('获取验证码成功');
        }
        return true;
    }

    public function checkCaptcha($captcha) {
        // 校验验证码
        $urlKey = 'captcha_check';
        $params = [
            'answer' => $captcha,
            'rand' => 'sjrand',
            'login_site' => 'E'
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));

        // 校验结果
        if ($res['result_code'] != 4) {
            Log::warning($res['result_message']);
            return false;
        } else {
            Log::info('验证码校验通过');
        }
        return true;
    }

    public function login($username, $password) {

        // 登陆
        $urlKey = 'login';
        $params = [
            'username' => $username,
            'password' => $password,
            'appid' => $this->appId,
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));
        if ($res['result_code'] != 0) {
            Log::error($res['result_message']);
        }


        // 权限校验
        $uamtk = $this->auth();
        $urlKey = 'uamauthclient';
        $params = [
            'tk' => $uamtk,
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));
        if ($res['result_code'] != 0) {
            Log::error($res['result_message']);
        } else {
            Log::info('欢迎【' . $res['username'] . '】登陆');
        }
        return true;
    }

    protected function auth() {

        $urlKey = 'auth';
        $params = [
            'appid' => $this->appId
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));

        // 校验结果
        if ($res['result_code'] != 0) {
            Log::error($res['result_message']);
        }
        return $res['newapptk'];
    }

    public function checkLogin() {

        if (!Cache::get(Consts::CACHE_LOGIN['key'])) {
            $urlKey = 'check_user';
            $params = [
                '_json_att' => ''
            ];
            $res = $this->__checkResponse($this->__send($urlKey, $params));

            // 已登陆
            if ($res['data']['flag'] == true) {
                Cache::set(Consts::CACHE_LOGIN['key'], 1, Consts::CACHE_LOGIN['ttl']);
            } else {
                return false;
            }
        }

        return true;
    }

    public function getAllStations() {
        $urlKey = 'station_names';
        $res = $this->__send($urlKey);

        $stations = explode("'", $res)[1];
        $stationsArr = explode('@', $stations);
        unset($stationsArr[0]);
        $stationArr = [];

        // 以车站名为key分组
        foreach ($stationsArr as $item)
        {
            $station = explode('|', $item);
            $stationArr[$station[1]] = $station[2];
        }

        return $stationArr;
    }

    public function queryTrains($date, $from, $to) {
        $allStationArr = $this->getAllStations();

        $urlKey = 'query_trains';
        $params = [
            'leftTicketDTO.train_date' => $date,
            'leftTicketDTO.from_station' => in_array($from, array_keys($allStationArr)) ? $allStationArr[$from] : '',
            'leftTicketDTO.to_station' => in_array($to, array_keys($allStationArr)) ? $allStationArr[$to] : '',
            'purpose_codes' => 'ADULT'
        ];

        // 单次请求
        $res = $this->__checkResponse($this->__send($urlKey, $params));

        if ($res['status'] !== true) {
            Log::warning('获取车次列表失败');
        } elseif (empty($res['data']['result'])) {
            Log::info("$from->$to 的车次坐席为空");
        }

        return $res['data']['result'];
    }

    public function multiQueryTrains($date, $from, $to, $multiCount) {
        $allStationArr = $this->getAllStations();

        $urlKey = 'query_trains';
        $params = [
            'leftTicketDTO.train_date' => $date,
            'leftTicketDTO.from_station' => in_array($from, array_keys($allStationArr)) ? $allStationArr[$from] : '',
            'leftTicketDTO.to_station' => in_array($to, array_keys($allStationArr)) ? $allStationArr[$to] : '',
            'purpose_codes' => 'ADULT'
        ];

        $count = 0;
        // 批量请求
        $resArr = $this->__send($urlKey, $params, null, $multiCount);
        if (is_array($resArr)) {
            foreach ($resArr as $resItem) {
                $resItem = $this->__checkResponse($resItem);
                if ($resItem['status'] === true && !empty($resItem['data']['result'])) {
                    $count ++;
                    $res['status'] = true;
                    $res['data']['result'] = array_merge(
                        (isset($res['data']['result']) ? $res['data']['result'] : []),
                        $resItem['data']['result']
                    );
                }
            }
        }

        if (!isset($res)) {
            Log::warning('未获取到车次列表，请手动检查IP是否被封');
            return null;
        } elseif ($res['status'] !== true) {
            Log::warning('获取车次列表失败');
        } elseif (empty($res['data']['result'])) {
            Log::info("$from->$to 的车次坐席为空");
        }

        return $res['data']['result'];
    }

    public function getPassengers() {
        if (!($passengers = Cache::get(Consts::CACHE_PASSENGER['key']))) {
            $urlKey = 'get_passengers';
            $res = $this->__checkResponse($this->__send($urlKey));
            $passengers = $res['data']['normal_passengers'];
            Cache::set(Consts::CACHE_PASSENGER['key'], $passengers);
        }

        if (empty($passengers)) {
            Log::warning('未获取到乘车人');
            sleep(Consts::RPC_FAILED_SECOND);
        }
        return $passengers;
    }

    public function submitOrder($trainInfo) {
        $urlKey = 'submit_order';
        $params = [
            'secretStr' => $trainInfo['secret_str'],
            'query_from_station_name' => $trainInfo['from_name'],
            'query_to_station_name' => $trainInfo['to_name'],
            'train_date' => $trainInfo['trip_date'],
            'back_train_date' => date('Y-m-d', time()),
            'tour_flag' => 'dc',
            'purpose_codes' => 'ADULT',
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));

        if (!empty($res['data']) || $res['data'] === 'N') {
            Log::info('出票成功，开始校验');
            return true;

        } else {
            $errMsg = is_array($res['messages']) ? $res['messages'][0] : $res['messages'];
            Log::warning($errMsg);
            sleep(Consts::RPC_FAILED_SECOND);
        }
        return false;
    }

    public function getOrderInfo() {
        $urlKey = 'init_order';
        $res = $this->__send($urlKey);

        preg_match("/var globalRepeatSubmitToken = \'(.*?)\'/", $res, $tokenMatch);
        preg_match("/var ticketInfoForPassengerForm\=(.*?)\;/", $res, $ticketInfoMatch);
        $token = $tokenMatch[1];
        if (!empty($ticketInfoMatch[1])) {
            $ticketInfo = json_decode(str_replace("'", '"', $ticketInfoMatch[1]), true);
        }

        return [
            'token' => $token,
            'ticket_info' => $ticketInfo ?? null,
        ];
    }

    public function checkOrder($passengerInfo, $orderInfo) {
        $urlKey = 'check_order';
        $params = [
            'passengerTicketStr' => $passengerInfo['passenger_ticket_str'],
            'oldPassengerStr' => $passengerInfo['old_passenger_str'],
            'REPEAT_SUBMIT_TOKEN' => $orderInfo['token'],
            'randCode' => '',
            'cancel_flag' => '2',
            'bed_level_order_num' => '000000000000000000000000000000',
            'tour_flag' => 'dc',
            '_json_att' => '',
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));

        if (!empty($res['data']) || $res['data']['submitStatus'] == true) {
            Log::info('车票验证通过，开始排队');
            return true;

        } else {
            Log::warning(is_array($res['messages']) ? $res['messages'][0] : $res['messages']);
            sleep(Consts::RPC_FAILED_SECOND);
        }
        return false;
    }

    public function getQueueCount($passengerInfo, $orderInfo) {
        $ticketInfo = $orderInfo['ticket_info'];

        // 格式化时间
        $trainDate = new \DateTime($ticketInfo['queryLeftTicketRequestDTO']['train_date']);
        $trainDate = $trainDate->format('D M d Y') . " 00:00:00 GMT+0800 (中国标准时间)";

        $urlKey = 'get_queue_count';
        $params = [
            'train_date' => $trainDate,
            'seatType' => $passengerInfo['seat_code'],
            'train_no' => $ticketInfo['queryLeftTicketRequestDTO']['train_no'],
            'stationTrainCode' => $ticketInfo['queryLeftTicketRequestDTO']['station_train_code'],
            'fromStationTelecode' => $ticketInfo['queryLeftTicketRequestDTO']['from_station'],
            'toStationTelecode' => $ticketInfo['queryLeftTicketRequestDTO']['to_station'],
            'leftTicket' => $ticketInfo['leftTicketStr'],
            'purpose_codes' => $ticketInfo['purpose_codes'],
            'train_location' => $ticketInfo['train_location'],
            'REPEAT_SUBMIT_TOKEN' => $orderInfo['token'],
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));

        if (!empty($res['status']) || $res['status'] == true) {

            $ticket = $res["data"]["ticket"];
            $count = isset($res["data"]["countT"]) ? intval($res["data"]["countT"]) : null;
            $ticketSum = (strpos(',', $ticket) !== false) ? array_sum(explode(',', $ticket)) : intval($ticket);

            if ($count == 0) {
                Log::info("无需排队，余票数 $ticketSum 张");
            } else {
                Log::info("当前排队人数 $count 人；余票数 $ticketSum 张");
            }
            return $count;

        }

        Log::warning(is_array($res['messages']) ? $res['messages'][0] : $res['messages']);

        return false;
    }

    public function confirmOrder($passengerInfo, $orderInfo) {
        $ticketInfo = $orderInfo['ticket_info'];

        // todo 下单校验验证码

        $urlKey = 'confirm_order';
        $params = [
            'passengerTicketStr' => $passengerInfo['passenger_ticket_str'],
            'oldPassengerStr' => $passengerInfo['old_passenger_str'],
            'REPEAT_SUBMIT_TOKEN' => $orderInfo['token'],
            'purpose_codes' => $ticketInfo['purpose_codes'],
            'key_check_isChange' => $ticketInfo['key_check_isChange'],
            'leftTicketStr' => $ticketInfo['leftTicketStr'],
            'train_location' => $ticketInfo['train_location'],
            'randCode' => '',
            'choose_seats' => '',
            'dwAll' => 'N',
            'whatsSelect' => 1,
            'seatDetailType' => '1',
            'roomType' => '1',
            '_json_att' => '',
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));

        if (!empty($res['data']['submitStatus']) && $res['data']['submitStatus'] == true) {
            Log::info('提交订单成功');
            return true;

        } elseif (empty($res['status']) || $res['status'] != true) {
            Log::warning(is_array($res['messages']) ? $res['messages'][0] : $res['messages']);
        } elseif (empty($res['data']['submitStatus']) || $res['data']['submitStatus'] != true) {
            Log::warning($res['data']['errMsg']);
        }

        return false;
    }

    public function getOrderNoComplete($lastOrder = true) {

        // 进入订单列表页，获取session
        $urlKey = 'init_order_no_complete';
        $params = [
            '_json_att' => '',
        ];
        $this->__send($urlKey, $params);

        // 获取未完成订单列表
        $urlKey = 'get_order_no_complete';
        $params = [
            '_json_att' => '',
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));

        if (empty($res['data']['orderDBList'])) {
            Log::info('未获取到未完成订单');
        } else {
            if ($lastOrder) {
                $orderId = ['data']['orderDBList'][0]['sequence_no'];
            } else {
                $orderId = ['data']['orderDBList'];
            }
        }
        return $orderId ?? false;
    }

    public function cancelOrderNoComplete($orderNo) {
        $urlKey = 'cancel_order_no_complete';
        $params = [
            'sequence_no' => $orderNo,
            'cancel_flag' => 'cancel_order',
            '_json_att' => '',
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));

        if (!empty($res['data']) || $res['data']['existError'] === 'N') {
            Log::info('取消未完成订单成功');
            return true;

        } else {
            Log::warning("取消未完成订单失败:$orderNo");
        }
        return false;
    }

    public function waitOrder() {
        $urlKey = 'get_order_wait_time';
        $params = [
            'random' => mt_rand(),
            'tourFlag' => 'dc',
            '_json_att' => '',
        ];
        $res = $this->__checkResponse($this->__send($urlKey, $params));

        if (empty($res['data']) || $res['data']['queryOrderWaitTimeStatus'] != true) {
            Log::warning("查询订单等待时间失败");
        } elseif ($res['data']['waitTime'] > 0) {
            Log::info("订单排队剩余时间:" . $res['data']['waitTime']);
        } elseif (empty($res['data']['orderId'])) {
            Log::warning($res['data']['msg']);

        } else {
            $orderId = $res['data']['orderId'];
            $successMsg = "订票成功，订单号：$orderId, 请访问12306，在30分钟内完成支付";
            Log::info($successMsg);
            Mail::send('抢票成功', $successMsg);
            return true;
        }
        return false;
    }

}