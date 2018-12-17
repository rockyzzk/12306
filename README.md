Rocky12306
=======================

购买火车票

- 余票监控，抢票
- 自动/手动识别验证码

## 依赖 

自动识别验证码，需要如下配置

- PHP 拓展：GD、Imagick

以下二选一

- 注册百度云应用（人工智能-图像识别）
- Tesseract OCR

## 安装

通过[Composer](http://getcomposer.org)安装依赖.

```bash
composer install
```

创建配置文件

```bash
cp conf/user.yaml.example conf/user.yaml
```

运行
```php
php run.php
```

