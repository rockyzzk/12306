<?php
/**
 * Desc: 邮件核心
 * User: zhangzekang
 * Date: 2018/12/10
 * Time: 下午4:34
 */

namespace Utils;

use \Core\Conf;
use \Core\Log;
use \Swift_SmtpTransport;
use \Swift_Mailer;
use \Swift_Message;

class Mail
{
    protected static $mailer = null;

    protected static $mailConf;

    protected static function init()
    {
        self::$mailConf = Conf::getConf('mail_conf', 'user');
    }

    protected static function checkConf() {
        if (empty(self::$mailConf['host']) ||
            empty(self::$mailConf['username']) ||
            empty(self::$mailConf['passcode']) ||
            empty(self::$mailConf['from_email']) ||
            empty(self::$mailConf['to_email'])) {

            Log::warning('请先到conf/user.yaml中配置邮箱');
            return false;
        }
        return true;
    }

    protected static function getMailer() {
        if (is_null(self::$mailer)) {

            self::init();
            if (!self::checkConf()) {
                return null;
            }

            $transport = (new Swift_SmtpTransport(self::$mailConf['host'], 25))
                ->setUsername(self::$mailConf['username'])
                ->setPassword(self::$mailConf['passcode'])
            ;
            self::$mailer = new Swift_Mailer($transport);
        }
        return self::$mailer;
    }

    protected static function createMessage($title, $msg) {
        $message = (new Swift_Message($title))
            ->setFrom([self::$mailConf['from_email'] => 'Rocky12306'])
            ->setTo(is_array(self::$mailConf['to_email']) ? self::$mailConf['to_email'] : [self::$mailConf['to_email']])
            ->setBody($msg)
        ;
        return $message;
    }

    public static function send($title, $msg) {
        $mailer = self::getMailer();
        if (empty($mailer)) {
            return null;
        }

        $message = self::createMessage($title, $msg);
        $result = $mailer->send($message);
        return $result;
    }
}