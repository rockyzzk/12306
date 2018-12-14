<?php
/**
 * Desc: 百度AI实现接口
 * User: zhangzekang
 * Date: 2018/12/10
 * Time: 上午11:45
 */

namespace sdk\baiduai;

require_once 'AipOcr.php';
require_once 'AipImageSearch.php';
require_once 'AipImageClassify.php';

use \Core\Conf;

class Main {

    private $app_id;

    private $api_key;

    private $secret_key;

    protected $ocrObj;

    protected $imgSearchObj;

    protected $imgRecoObj;

    function __construct()
    {
        $conf = Conf::getConf('baiduai_conf', 'user');
        $this->app_id = $conf['app_id'];
        $this->api_key = $conf['api_key'];
        $this->secret_key = $conf['secret_key'];

        $this->ocrObj = new \AipOcr($this->app_id, $this->api_key, $this->secret_key);
        $this->imgSearchObj = new \AipImageSearch($this->app_id, $this->api_key, $this->secret_key);
        $this->imgRecoObj = new \AipImageClassify($this->app_id, $this->api_key, $this->secret_key);
    }

    public function recognizeWord($imgPath) {

        $image = file_get_contents($imgPath);

        // 调用通用文字识别, 图片参数为本地图片
        return $this->ocrObj->basicGeneral($image);
    }

    public function recognizeImg($imgPath) {

        $image = file_get_contents($imgPath);

        // 调用通用文字识别, 图片参数为本地图片
        return $this->imgRecoObj->advancedGeneral($image);
    }

    /**
     * 接口有QPS限制
     * @param $imgPathArr
     * @return array
     */
    public function multiRecognizeImg($imgPathArr) {

        $images = [];
        foreach ($imgPathArr as $imgPath) {
            $images[] = file_get_contents($imgPath);
        }

        // 调用通用文字识别, 图片参数为本地图片
        return $this->imgRecoObj->multiAdvancedGeneral($images);
    }
}

return new Main();