<?php
/**
 * Desc: 配置核心
 * User: zhangzekang
 * Date: 2018/11/22
 * Time: 下午7:37
 */

namespace Core;

use \Symfony\Component\Yaml\Yaml;

class Conf
{
    protected static $defaultFile = 'global';

    /**
     * @param null $key    key为null，返回全部
     * @param null $fileName
     * @return null
     */
    public static function getConf($key = null, $fileName = null) {
        $values = self::_getYaml($fileName);
        $data = self::_getData($values, $key);
        if (is_null($data)) {
            Log::error("配置文件 $fileName.yaml 中的 $key 未设置");
        }
        return $data;
    }

    protected static function _getYaml($file = null) {

        if (empty($file)) {
            $file = self::$defaultFile;
        }

        $path = CONF_PATH . '/' . $file . '.yaml';

        if (!file_exists($path)) {
            return null;
        }

        return Yaml::parseFile($path);
    }

    protected static function _getData($values, $keyDir) {

        if (is_null($keyDir)) {
            return $values;
        }

        $keyArr = explode('/', $keyDir);
        while(($keyItem = array_shift($keyArr)) !== null) {
            if (isset($values[$keyItem])) {
                $values = $values[$keyItem];
            } else {
                $values = null;
                break;
            }
        }

        return $values;
    }
}