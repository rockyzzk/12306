<?php
/**
 * Desc: 自动加载
 * User: zhangzekang
 * Date: 2018/11/22
 * Time: 下午7:12
 */

class Loader
{
    public static $pathMap = array(
        'Rpc' => RPC_PATH,
        'Base' => LIB_PATH . DIRECTORY_SEPARATOR . 'base',
        'Core' => LIB_PATH . DIRECTORY_SEPARATOR . 'core',
        'Define' => LIB_PATH . DIRECTORY_SEPARATOR . 'define',
        'Utils' => UTILS_PATH,
    );

    public static function autoload($class)
    {
        $file = self::findFile($class);
        if (file_exists($file)) {
            self::requireFile($file);
        }
    }

    private static function findFile($class)
    {
        // 顶级命名空间对应目录
        $topName = substr($class, 0, strpos($class, '\\'));
        $topDir = self::$pathMap[$topName];

        // 文件路径
        $filePath = substr($class, strlen($topName)) . '.php';
        $filePath = strtr($topDir . $filePath, '\\', DIRECTORY_SEPARATOR);

        return $filePath;
    }

    private static function requireFile($file)
    {
        if (is_file($file)) {
            require_once $file;
        }
    }
}