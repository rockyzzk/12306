<?php
/**
 * Desc: 百度图片
 * User: zhangzekang
 * Date: 2018/12/6
 * Time: 下午7:00
 */

namespace Rpc;

use \Base\RpcBase;
use \Core\Conf;

class BaiduImg extends RpcBase {

    protected $urls;

    function __construct()
    {
        // rpc配置
        $conf = Conf::getConf(null, 'baiduimg');
        $this->urls = $conf['urls'];

        $this->host = $this->urls['protocol'] . '://' . $this->urls['host'];
    }

    public function query($imgPath) {

        $urlKey = 'query';
        $params['multipart'][0] = [
            'form_name' => 'filedata',
            'content' => fopen($imgPath, 'r')
        ];
        $res = $this->_post($this->urls[$urlKey]['path'], $params, null);

        return $this->queryByRedirectUri($res);
    }

    public function multiQuery($imgPathArr) {

        $keywordArr = [];
        $urlKey = 'query';
        foreach ($imgPathArr as $key => $imgPath) {
            $params['multipart'][$key] = [
                'form_name' => 'filedata',
                'content' => fopen($imgPath, 'r')
            ];
        }

        $resArr = $this->_post($this->urls[$urlKey]['path'], $params ?? null, null, count($imgPathArr));

        foreach ($resArr as $res) {
            $keywordArr[] = $this->queryByRedirectUri($res);
        }

        return $keywordArr;
    }

    protected function queryByRedirectUri($uri) {
        $res = $this->_get($uri, null);
        preg_match("/\'multitags\'\:(.*?)\,/", $res, $match);
        return $match[1];
    }

}