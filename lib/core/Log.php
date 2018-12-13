<?php
/**
 * Desc: 日志核心
 * User: zhangzekang
 * Date: 2018/11/27
 * Time: 上午10:56
 */

namespace Core;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Log
{
    protected static $runtimeLogger = null;

    protected static $rpcLogger = null;

    protected static function getLogger($logger) {
        switch ($logger) {
            case 'rpc' :
                return self::getRpcLogger();
            default :
                return self::getRuntimeLogger();
        }
    }

    protected static function getRuntimeLogger() {
        if (is_null(self::$runtimeLogger)) {
            $loggerName = 'runtime';
            $logger = new Logger($loggerName);
            $logger->pushHandler(new StreamHandler(LOG_PATH . '/' . $loggerName . '.log', Logger::INFO));
            $logger->pushHandler(new StreamHandler(LOG_PATH . '/' . $loggerName . '.wf.log', Logger::WARNING));
            $logger->pushHandler(new StreamHandler(LOG_PATH . '/' . $loggerName . '.err.log', Logger::ERROR));
            self::$runtimeLogger = $logger;
        }
        return self::$runtimeLogger;
    }

    protected static function getRpcLogger() {
        if (is_null(self::$rpcLogger)) {
            $loggerName = 'rpc';
            $logger = new Logger($loggerName);
            $logger->pushHandler(new StreamHandler(LOG_PATH . '/' . $loggerName . '.log', Logger::INFO));
            $logger->pushHandler(new StreamHandler(LOG_PATH . '/' . $loggerName . '.wf.log', Logger::WARNING));
            $logger->pushHandler(new StreamHandler(LOG_PATH . '/' . $loggerName . '.err.log', Logger::ERROR));
            self::$rpcLogger = $logger;
        }
        return self::$rpcLogger;
    }

    public static function console($msg, $logger, $level) {

        switch ($level) {
            case Logger::ERROR:
                $flag = ' 【错误】 '; break;
            case Logger::WARNING:
                $flag = ' 【警告】 '; break;
            default :
                $flag = ' ';
        }

        switch ($logger) {
            case 'rpc' :
                break;
            default :
                printf(date('Y-m-d H:i:s', time()) . $flag . (string)$msg . PHP_EOL);
        }

        return true;
    }

    public static function info($msg, $logger = null) {
        self::console($msg, $logger, Logger::INFO);
        return self::getLogger($logger)->info($msg);
    }

    public static function warning($msg, $logger = null) {
        self::console($msg, $logger, Logger::WARNING);
        return self::getLogger($logger)->warning($msg);
    }

    public static function error($msg, $logger = null) {
        self::console($msg, $logger, Logger::ERROR);
        self::getLogger($logger)->error($msg);
        die;
    }
}