# tg-bot
一个简单的Telegram 机器人，用于将用户消息转发给管理员，管理员回复消息转发给客户。

## 前置依赖
使用了SQLite3本地存储消息id和用户id的对应关系，除此之外没有其它额外依赖。  

## 配置使用
假设你的域名为`xxx.com`，通过path `/tg_bot.php`能够访问到程序。

+ 修改`tg_bot.php`配置
```php
define('ADMIN_UID', 666666);        // 管理员的 Telegram ID，可以从 @userinfobot 获取
define('BOT_TOKEN', '12345:xxxxxxx'); //  Telegram Bot Token，从 @BotFather 获取
// define('WEBHOOK', '/tg_bot.php');
define('WEBHOOK', '/' . $currentFileName);  // WEBHOOK地址，使用该path能够访问到该php的地址
```

+ 将`tg_bot.php`拷贝到了特定目录，确保`https://xxx.com/tg_bot.php`能够访问  
    若不能访问，请将`WEBHOOK`修改为能够访问的path

+ 注册webhook
访问`https://xxx.com/tg_bot.php?do=register`

+ 正常使用即可