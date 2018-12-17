Rocky12306
=======================

购买火车票

- 余票监控，自动抢票
- 自动/手动识别验证码

## 依赖 

自动识别验证码，需要如下拓展

- PHP 拓展：GD、Imagick

以下二选一

- 注册百度云人工智能应用 [人工智能-图像识别-创建应用](https://console.bce.baidu.com/ai#/ai/imagesearch/overview/index)（需包含"文字识别、图像识别"接口）
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
```bash
php run.php
```
## 运行效果

![image](https://github.com/rockyzzk/12306/blob/master/%E8%BF%90%E8%A1%8C%E6%95%88%E6%9E%9C%E5%9B%BE.png)
