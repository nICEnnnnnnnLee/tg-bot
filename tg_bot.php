<?php

$currentFilePath = __FILE__;
$currentFileName = basename($currentFilePath);

define('ADMIN_UID', 666666);        // 管理员的 Telegram ID，可以从 @userinfobot 获取
define('BOT_TOKEN', '12345:xxxxxxx'); //  Telegram Bot Token，从 @BotFather 获取
define('BOT_HEADER_SECRET', '114514'); //  随机字符串，字母 + 数字，用于头部字段X-TELEGRAM-BOT-API-SECRET-TOKEN鉴权
// define('WEBHOOK', '/tg_bot.php');
define('WEBHOOK', '/' . $currentFileName);  // WEBHOOK地址，请使用能够访问到该php的地址
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('DB_FILE', 'tg.db');             // sqlite db 文件路径

// 定义自定义键盘按钮
$keyboard = [
    'keyboard' => [
        ['/help', '/getid']
    ],
    'resize_keyboard' => true,
    'one_time_keyboard' => false
];
$help_text = "🤖 *机器人帮助文档*\n\n"
    . "/start - 显示本帮助文档\n"
    . "/help - 显示本帮助文档\n"
    . "/getid - 返回您的数字用户ID\n"
    . "发送其它内容会将消息转发给admin\n"
    . "\n点击下面菜单按钮快速发送命令。";


if (isset($_GET['do'])) {
  $do = htmlspecialchars($_GET['do']); // 转义特殊字符
  if($do == 'register'){
      print_r(setWebhook());
  }else if($do == 'unregister'){
      print_r(deleteWebhook());
  }else{
      echo $do;
  }
  
  exit;
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerName = str_replace('HTTP_', '', $key);
        $headers[$headerName] = $value;
    }
}

if (isset($headers['X_TELEGRAM_BOT_API_SECRET_TOKEN']) && $headers['X_TELEGRAM_BOT_API_SECRET_TOKEN'] == BOT_HEADER_SECRET) {
    // 
} else {
    header("HTTP/1.1 403 Forbidden");
    $errorMessage = "Forbidden: You're unauthorized to visit here.";
    echo $errorMessage;
    exit; // 终止脚本执行
}

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])){
    handleMessage($update['message']);
}
    
echo "Ok";

/**
 * 获取用户ID
 *
 * @param string|int $msg_id 消息ID
 * @return string|null 用户ID，如果未找到则返回 null
 */
function getUserId($msg_id){
    try {
        $db = new SQLite3(DB_FILE);
        $stmt = $db->prepare("SELECT user_id FROM tg_msg WHERE msg_id = :msg_id");
        $stmt->bindValue(':msg_id', $msg_id, SQLITE3_TEXT); // 存储为TEXT，避免int溢出
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $db->close();
        if ($row) {
            return $row['user_id']; // 返回字符串
        } else {
            return null;
        }

    } catch (Exception $e) {
        // 错误处理
        error_log("Error in getUserId function: " . $e->getMessage()); // 记录错误日志
        return null; // 返回 null 表示出错
    }
}

/**
 * 保存消息ID和用户ID
 *
 * @param string|int $msg_id 消息ID
 * @param string|int $user_id 用户ID
 * @return bool 是否保存成功
 */
function setId($msg_id, $user_id){
    try {
        $db = new SQLite3(DB_FILE);

        $db->exec("CREATE TABLE IF NOT EXISTS tg_msg (msg_id TEXT PRIMARY KEY, user_id TEXT)"); // 使用TEXT类型
        $stmt = $db->prepare("INSERT OR REPLACE INTO tg_msg (msg_id, user_id) VALUES (:msg_id, :user_id)"); // 使用 INSERT OR REPLACE，如果msg_id已经存在则更新user_id
        $stmt->bindValue(':msg_id', $msg_id, SQLITE3_TEXT); // 存储为TEXT，避免int溢出
        $stmt->bindValue(':user_id', $user_id, SQLITE3_TEXT); // 存储为TEXT，避免int溢出

        $result = $stmt->execute();
        $db->close();
        return $result !== false; //  如果执行成功,则返回 true,否则返回 false

    } catch (Exception $e) {
        // 错误处理
        error_log("Error in setId function: " . $e->getMessage()); // 记录错误日志
        return false; // 返回 false 表示出错
    }
}

function handleMessage($message) {
    if($message['text'] == '/start' || $message['text'] == '/help'){
        global $help_text;
        sendMessage($message['chat']['id'], $help_text);
    }else if($message['text'] == '/getid'){
        $user_id = $message['from']['id'] ?? null;
        $reply_msg =  "您的ID是：`$user_id`";
        sendMessage($message['chat']['id'], $reply_msg);
    }else if($message['chat']['id'] == ADMIN_UID){
        // 如果是管理员转发的消息
        if (isset($message['reply_to_message']) && isset($message['reply_to_message']['chat']) ){
            
            // $chat_key = 'msg-map-'  . $message['reply_to_message']['message_id'];
            $chat_key = $message['reply_to_message']['message_id'];
            $chat_id = getUserId($chat_key);
            if(is_null($chat_id)){
                sendMessage(ADMIN_UID, 'Cant find msg channel:' . $chat_key);
            }else{
                copyMessage($chat_id, $message['chat']['id'], $message['message_id']);
            }
        }else{
            sendMessage($message['chat']['id'], 'Please choose msg to reply.');
        }
    }else{
        $chat_id = $message['chat']['id'];
        $forward = forwardMessage(ADMIN_UID, $chat_id, $message['message_id']);
        if(isset($forward['ok'])){
            // forwardReq.result.message_id
            // $chat_key = 'msg-map-'  . $forward['result']['message_id'];
            $chat_key = $forward['result']['message_id'];
            // sendMessage(ADMIN_UID, 'chat_key: ' . $chat_key . ' , chat_id' . $chat_id . $headers['X_TELEGRAM_BOT_API_SECRET_TOKEN']);
            setId($chat_key, $chat_id);
        }
    }
}

function reqTG($method, $data) {
    global $keyboard;
    $url = API_URL . $method;
    $data['reply_markup'] = json_encode($keyboard);
    $data['parse_mode'] = 'Markdown';
    $options = [
        'http' => [
            'method'  => 'POST',
            'content' => json_encode($data),
            'header'  => "Content-Type: application/json\r\n",
            #'proxy'           => 'tcp://127.0.0.1:1081',
            #'request_fulluri' => true,
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $result = json_decode($result, true);
    return $result;
}

function sendMessage($chat_id, $text) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    $result = reqTG('sendMessage', $data);
    return $result;
}

function copyMessage($chat_id, $from_chat_id, $message_id ) {
    $data = [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    $result = reqTG('copyMessage', $data);
    return $result;
}

function forwardMessage($chat_id, $from_chat_id, $message_id ) {
    $data = [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    $result = reqTG('forwardMessage', $data);
    return $result;
}

function setWebhook1() {
    $currentDomain = $_SERVER['HTTP_HOST'];
    $url = 'https://' . $currentDomain . WEBHOOK;
    $url = API_URL . 'setWebhook?url=' . $url . '&secret_token=' . BOT_HEADER_SECRET;
    $result = file_get_contents($url);
    return $result;
}

function setWebhook() {
    $currentDomain = $_SERVER['HTTP_HOST'];
    $data = [
        'url' => 'https://' . $currentDomain . WEBHOOK,
        'secret_token' => BOT_HEADER_SECRET,
    ];
    $result = reqTG('setWebhook', $data);
    return $result;
}

function deleteWebhook() {
    $url = API_URL . 'deleteWebhook';
    $result = file_get_contents($url);
    return $result;
}

?>



