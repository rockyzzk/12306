<?php
/**
 * Desc: 识别验证码
 * User: zhangzekang
 * Date: 2018/12/6
 * Time: 下午12:42
 */

namespace Utils;

use \Core\Conf;
use \Core\Log;
use \Define\Consts;
use \Rpc\BaiduImg;
use \Sdk\baiduai\Main as BaiduAi;
use \Intervention\Image\ImageManager;
use \thiagoalessio\TesseractOCR\TesseractOCR;

class Captcha
{
    protected $captchaPath;

    protected $captchaWordPath;

    protected $captchaSubPath;

    protected $isAutoCaptcha;

    protected $isAi;

    protected $managerObj;

    protected $baiduImgObj;

    protected $baiduAiObj;

    function __construct()
    {
        $this->captchaPath = ROOT_PATH . '/' . Conf::getConf('captcha_path');
        $this->captchaWordPath = ROOT_PATH . '/' . Conf::getConf('captcha_word_path');
        $this->captchaSubPath = ROOT_PATH . '/' . Conf::getConf('captcha_sub_path');
        $this->isAutoCaptcha = Conf::getConf('is_auto_captcha', 'user');
        $this->isAi = $this->checkBaiduaiConf();
        $this->checkAutoEnv();

        $this->managerObj = new ImageManager(array('driver' => 'imagick'));
        $this->baiduImgObj = new BaiduImg();
        if ($this->isAi) {
            $this->baiduAiObj = new BaiduAi();
        }
    }

    private function checkBaiduaiConf() {
        $aiConf = Conf::getConf('baiduai_conf', 'user');

        if (empty($aiConf['app_id']) ||
            empty($aiConf['api_key']) ||
            empty($aiConf['secret_key'])) {

            return false;
        }
        return true;
    }

    private function checkAutoEnv() {

        // check 图像处理，依赖：GD Library、Imagick PHP extension
        if ($this->isAutoCaptcha) {
            if (!extension_loaded('gd')) {
                Log::error('请检查PHP环境是否引入 GD 库');
            }
            if (!extension_loaded('imagick')) {
                Log::error('请检查PHP环境是否引入 Imagick 拓展');
            }
        }

        // check tesseract-ocr
        if ($this->isAutoCaptcha && !$this->isAi) {

            $cmd = stripos(PHP_OS, 'win') === 0
                ? 'where.exe "tesseract" > NUL 2>&1'
                : 'type "tesseract" > /dev/null 2>&1';
            system($cmd, $exitCode);

            if ($exitCode != 0) {
                Log::error('请检查环境是否安装 Tesseract OCR');
            }
        }
    }

    public function recognize() {

        if ($this->isAutoCaptcha) {
            $input = $this->auto();
        } else {
            $input = $this->manual();
        }

        $captcha = $this->transferPosition($input);

        return $captcha;
    }

    protected function transferPosition ($numStr) {
        if (empty($numStr)) {
            return '';
        }

        $numStr = str_replace('，', ',', $numStr);
        $numStr = str_replace(' ', '', $numStr);
        $numArr = array_unique(explode(',', $numStr));

        $positions = [];

        foreach ($numArr as $num) {
            $num = intval($num);
            $key = ($num >= 1 && $num <= 8) ? $num - 1 : 0;

            list($x, $y) = Consts::CAPTCHA_POSITION[$key];

            if (!empty($x) && !empty($y)) {
                $pos = $x . ',' . $y;
                $positions[] = $pos;
            }
        }
        $positions = implode(',', $positions);

        return $positions;
    }

    protected function manual() {
        printf('
                *****************
                | 1 | 2 | 3 | 4 |
                *****************
                | 5 | 6 | 7 | 8 |
                *****************
                
                请输入验证码（例如选择第一和第二张，输入1,2）' . PHP_EOL
        );
        exec('open ' . $this->captchaPath);
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        return $input;
    }

    protected function auto() {

        $imgKeyArr = [];
        $this->getWord();
        $this->getSubImgs();

        // 识别汉字
        $keywordByWord = $this->recognizeWord($this->captchaWordPath);
        $keywordByWordArr = preg_split("//u", $keywordByWord, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($keywordByWord)) {
            return '';
        }

        // 遍历识别每张子图
        $keywordByImg = $this->recognizeAllSubImg();

        // 遍历每个汉字
        foreach ($keywordByWordArr as $keywordByWordItem) {
            // 遍历每张图
            foreach ($keywordByImg as $imgKey => $keywordByImgItem) {
                if (mb_strpos($keywordByImgItem, $keywordByWordItem) !== false) {
                    if (!in_array($imgKey, $imgKeyArr)) {
                        $imgKeyArr[] = $imgKey;
                    }
                }
            }
        }

        $captcha = implode(',', $imgKeyArr);
        Log::info('自动识别验证码:'.$captcha);
        return $captcha;
    }

    protected function recognizeWord($imgPath) {

        if ($this->isAi) {
            // 百度AI (50000次/天免费)
            $keywordArr = $this->baiduAiObj->recognizeWord($imgPath);
            if (!empty($keywordArr['words_result'][0]['words'])) {
                $keyword = $keywordArr['words_result'][0]['words'];
            }
        } else {
            // 本地OCR
            $keyword =  (new TesseractOCR($imgPath))
                ->lang('chi_sim', 'chi_tra')
                ->run();
        }

        return $keyword ?? '';
    }

    protected function recognizeAllSubImg() {

        $keywordByImgArr = [];

        if ($this->isAi) {
            // 百度AI (500次/天免费)
            for ($key = 1; $key <= 8; $key++) {
                $keywordArr = $this->baiduAiObj->recognizeImg($this->captchaSubPath . $key . '.jpg');
                if (!empty($keywordArr['result'])) {
                    $keyword = implode('|', array_column($keywordArr['result'], 'keyword'));
                    $keywordByImgArr[$key] = $keyword ?? '';
                }
            }
        }

        if (empty($keywordByImgArr)) {
            // 百度识图
            for ($key = 1; $key <= 8; $key++) {
                $imgPathArr[$key] = $this->captchaSubPath . $key . '.jpg';
            }
            $keywordByImgArr = $this->baiduImgObj->multiQuery($imgPathArr ?? []);
        }

        return $keywordByImgArr ?? [];
    }

    private function getWord() {
        return $this->managerObj
            ->make($this->captchaPath)
            ->crop(130, 25, 120, 2)
            ->save($this->captchaWordPath);
    }

    private  function getSubImgs() {
        $subImgs = [];
        $key = 0;
        $width = 67;
        $height = 67;
        $topX = 5;
        $topY = 41;
        $space = 5;

        for ($y = 0; $y < 2; $y++) {
            for ($x = 0; $x < 4; $x++) {

                $subImg = $this->managerObj
                    ->make($this->captchaPath)
                    ->crop($width, $height, $topX + ($space + $width) * $x, $topY + ($space + $height) * $y)
                    ->save($this->captchaSubPath . ($key + 1) . '.jpg');

                $key++;
                $subImgs[$key] = $subImg;
            }
        }
        return $subImgs;
    }

}