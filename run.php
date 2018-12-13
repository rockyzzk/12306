<?php
/**
 * Desc: 运行文件
 * User: zhangzekang
 * Date: 2018/11/22
 * Time: 下午7:31
 */

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/lib/Init.php';

Init::start();

$app = require_once __DIR__.'/main.php';
$app->start();
