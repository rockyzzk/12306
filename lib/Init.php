<?php
/**
 * Desc: 初始化
 * User: zhangzekang
 * Date: 2018/11/22
 * Time: 下午5:33
 */

require_once __DIR__.'/../lib/Loader.php';

class Init
{

    public static function start()
    {
        // 初始化基础环境
        self::_basicEnv();

        // 注册自动加载
        self::_autoLoad();

        // PHP 配置
        self::_phpIni();
    }

    private static function _basicEnv()
    {
        define('ROOT_PATH', realpath(dirname(__FILE__) . '/../'));
        define('CONF_PATH', ROOT_PATH.'/conf');
        define('RPC_PATH', ROOT_PATH.'/rpc');
        define('LIB_PATH', ROOT_PATH.'/lib');
        define('LOG_PATH', ROOT_PATH.'/log');
        define('UTILS_PATH', ROOT_PATH.'/utils');
        define('SDK_PATH', ROOT_PATH.'/sdk');
    }

    private static function _autoLoad()
    {
        spl_autoload_register('Loader::autoload');
    }

    private static function _phpIni()
    {
        ini_set('error_reporting', E_ALL ^ E_NOTICE);
        ini_set('display_errors', true);
        ini_set('log_errors', true);
        ini_set('log_errors_max_len', 1024);
        ini_set('error_log', LOG_PATH . '/php-err.log');
    }

}