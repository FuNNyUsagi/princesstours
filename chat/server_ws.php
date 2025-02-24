<?php
use Workerman\Worker;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPMailer\PHPMailer\PHPMailer;
use websoket\chat\Users;

require_once __DIR__ . '/../vendor/autoload.php';

require_once 'Users.php';

//список коннектов
//$users = [];

//Инициализируем класс-реестр с юзерами
$users = new Users();

//создаём ws сервер, к которому будут подключаться наши юзеры
$wsWorker = new Worker('websocket://0.0.0.0:4369');
$wsWorker->count = 1;

//Логер-самописька
function LogerTXT($str) {
    $log  = "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("d.m.Y H:i:s").PHP_EOL.
        "Attempt: Failed".PHP_EOL.
        "Log: ".$str.PHP_EOL.
        "-------------------------".PHP_EOL;

    file_put_contents('error_log.txt', $log, LOCK_EX | FILE_APPEND);
}

//Проверка на изображение
function is_url_image($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    $output = curl_exec($ch);
    curl_close($ch);

    $headers = array();
    foreach(explode("\n",$output) as $line){
        $parts = explode(':' ,$line);
        if(count($parts) == 2){
            $headers[trim($parts[0])] = trim($parts[1]);
        }

    }

    return isset($headers["Content-Type"]) && strpos($headers['Content-Type'], 'image/') === 0;
}

$wsWorker->onMessage = function ($connection, $data) use (&$users, $wsWorker) {
    $data = json_decode($data, true);
    $request = [];

    switch ($data['section']) {
        case 'authorization':
            $user_id = $data['data']['userID'];
            $pswd = $data['data']['identif'];
            $err_flag = false;

            /*if (array_key_exists($user_id, $users)) {
                if (!in_array($connection, $users[$user_id])) {
                    $users[$user_id][] = $connection;
                }
            } else {
                $users[$user_id][] = $connection;
            }*/
            $users::setNewConnectByUserID($user_id, $connection);

            $servername = "localhost";
            $username = "intact_user";
            $password = "0H7s5N3t";
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT COUNT(*) AS flag FROM ph_users as pu WHERE pu.password = :pswd AND pu.id = :uid";
                $q = $conn->prepare($sql);
                $q->bindParam(':pswd', $pswd, PDO::PARAM_STR);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $cnt = $res['flag'];

                /********************************************************************************************************/
                $sql = "SELECT user_id FROM ph_chat_user_to_dialog CUD WHERE CUD.user_id != :uid AND CUD.dialog_id in (
                	SELECT dialog_id FROM ph_chat_user_to_dialog
                	WHERE user_id = :uid
                );";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $usrs = $q->fetchAll(PDO::FETCH_ASSOC);

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }

            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            if ($cnt == 0) {
                $request['section'] = 'authorization';
                $request['status'] = false;
                $json = json_encode($request);
                $connection->send($json);
            } else {
                //Отсылаем себе инфу по авторизации
                $request['section'] = 'authorization';
                $request['status'] = true;
                $json = json_encode($request);
                $connection->send($json);

                //Отсылаем всем, кто онлайн и с кем у юзера есть диалог, что типа челик онлайн
                $request['section'] = 'userOnline';
                $request['userID'] = $user_id;
                $json = json_encode($request);
                foreach ($usrs as $us) {
                    $usr_to = $us['user_id'];
                    //if (array_key_exists($usr_to, $users)) {
                    if ($users::checkUserID($usr_to)) {
                        //$ws_connections = $users[$usr_to];
                        $ws_connections = $users::getConnectionsByUserID($usr_to);
                        foreach ($ws_connections as $ws) {
                            $ws->send($json);
                        }
                    }
                }
            }

            //Проверяем, является ли юзер Ф или нет
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT COUNT(*) AS flag FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $flag = (int)$res['flag'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }
            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            //Если юзер является Ф, то
            if ($flag) {
                //...переопределяем $user_id как id оператора
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT UFO.operator_id FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $user_id = (int)$res['operator_id'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
            }

            //Обнуляем статусы таблицы ph_chat_message_to_email для данного юзера
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "UPDATE `ph_chat_message_to_email` SET flag = 1 WHERE user_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }
            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }
            break;
        case 'getDialogList':
            $user_id = $data['data']['userID']; //id юзера. Это мб id реального юзера либо Ф
            $err_flag = false;

            /*Отправляем список диалогов как простому юзеру*/
            $servername = "localhost";
            $username = "intact_user";
            $password = "0H7s5N3t";
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT cud.dialog_id AS idDialog,
                    	cud.user_id AS userToID, 
                        ua.fullname AS dialogName,
                        ua.avatar,
                        DATE_FORMAT(cm1.date_create, '%d.%m.%Y %H:%i:%s') AS lastMessageDate,
                        CASE WHEN cm1.status = 1 THEN '(Sticker)' ELSE cm1.message END AS lastMessage,
                        (SELECT COUNT(*) FROM ph_chat_message WHERE sender_id != :uid AND view_message = 0 AND dialog_id = cud.dialog_id) AS countNewMessage
                    FROM ph_chat_user_to_dialog AS cud
                    
                    JOIN ph_user_attributes AS ua ON cud.user_id = ua.internalKey
                    
                    LEFT JOIN ph_chat_message AS cm1 ON cud.dialog_id = cm1.dialog_id
                    
                    LEFT OUTER JOIN ph_chat_message cm2 ON (cud.dialog_id = cm2.dialog_id AND 
                        (cm1.date_create < cm2.date_create OR (cm1.date_create = cm2.date_create AND cm1.id < cm2.id)))
                        
                    WHERE cud.dialog_id IN (SELECT dialog_id FROM ph_chat_user_to_dialog WHERE user_id = :uid) AND cud.user_id != :uid AND cm2.id IS NULL";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetchAll(PDO::FETCH_ASSOC);
                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }

            $full_res = [];
            foreach($res as $r) {
                //if (array_key_exists($r['userToID'], $users)) {
                if ($users::checkUserID($r['userToID'])) {
                    $r['online'] = true;
                } else {
                    $r['online'] = false;
                }
                $full_res[] = $r;
            }

            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);
                $connection->send($json);
            } else {
                $request['section'] = 'setDialogList';
                $request['data'] = $full_res;
                $json = json_encode($request);

                //$ws_connections = $users[$user_id];
                $ws_connections = $users::getConnectionsByUserID($user_id);
                foreach ($ws_connections as $ws) {
                    $ws->send($json);
                }
            }

            /*Отправляем оповещение как для оператора, если на каком-либо Ф есть непрочитанное сообщение*/
            $flag_new = false;
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                /*Проверяем есть ли новое сообщение на к-л Ф*/
                $sql = "SELECT COUNT(*) AS flag from (SELECT UFO.user_id,
                    	(SELECT COUNT(*) FROM ph_chat_message WHERE sender_id != UFO.user_id AND view_message = 0 AND dialog_id IN (SELECT dialog_id FROM ph_chat_user_to_dialog WHERE user_id = UFO.user_id)) AS cnt
                    FROM ph_users_for_operator UFO
                    WHERE UFO.operator_id = (SELECT operator_id 
                    	FROM ph_users_for_operator
                    	WHERE user_id = :uid)) AS tmp WHERE tmp.cnt > 0;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                /*Если не 0, то составляем список Ф оператора*/
                if ($res['flag'] != 0) {
                    $flag_new = true;

                    $sql = "SELECT UFO.user_id,
                        	(SELECT COUNT(*) FROM ph_chat_message WHERE sender_id != UFO.user_id AND view_message = 0 AND dialog_id IN (SELECT dialog_id FROM ph_chat_user_to_dialog WHERE user_id = UFO.user_id)) AS cnt
                        FROM ph_users_for_operator UFO
                        WHERE UFO.operator_id = (SELECT operator_id 
                        	FROM ph_users_for_operator
                        	WHERE user_id = :uid);";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    $res = $q->fetchAll(PDO::FETCH_ASSOC);
                }

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }

            if ($flag_new) {
                //Отсеиваем Ф, у которых сообщений новых не было
                $user_f = [];
                foreach ($res as $rs) {
                    if ($rs['cnt'] != 0) {
                        $user_f[] = $rs;
                    }
                }

                //Среди списка Ф ищем те, что онлайн, и передаём им оповещения. PS:в идеале должен быть онлайн только 1 Ф (но мы-то знаем этих деб.. юзеров)
                foreach($res as $r) {
                    //if (array_key_exists($r['user_id'], $users)) {
                    if ($users::checkUserID($r['user_id'])) {
                        $request = [];
                        $request['section'] = 'newMsgForOperator';
                        $request['data'] = $user_f;

                        $json = json_encode($request);
                        //$ws_connections = $users[$r['user_id']];
                        $ws_connections = $users::getConnectionsByUserID($r['user_id']);
                        foreach ($ws_connections as $ws) {
                            $ws->send($json);
                        }
                    }
                }
            }
            break;
        case 'getMessageList':
            $user_id = $data['data']['userID'];
            $dialog_id = $data['data']['dialogID'];
            $offset = $data['data']['offset'];
            $limit = $data['data']['limit'];
            $err_flag = false;

            $servername = "localhost";
            $username = "intact_user";
            $password = "0H7s5N3t";
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT
                        CASE WHEN pcm.status = 1 THEN '(Sticker)' ELSE pcm.message END AS lastMessageText,
                        DATE_FORMAT(pcm.date_create, '%d.%m.%Y %H:%i:%s') AS lastMessageDate,
                        (SELECT COUNT(*) FROM ph_chat_message WHERE sender_id != :uid AND view_message = 0 AND dialog_id = :did) AS countNewMessage
                    FROM ph_chat_message AS pcm
                    WHERE pcm.dialog_id = :did
                    ORDER BY id DESC
                    LIMIT 1";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->bindParam(':did', $dialog_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }

            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            $dialog_data = $res;

            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT * FROM (SELECT 
                			pcm.sender_id as senderID,
                        	pcm.id,
                        	pcm.status,
                            pcm.message,
                            DATE_FORMAT(pcm.date_create, '%d.%m.%Y %H:%i:%s') AS dateCreate,
                            pcm.view_message
                		FROM ph_chat_message AS pcm
                        WHERE pcm.dialog_id = :did
                        ORDER BY pcm.id DESC
                        LIMIT :o, :l) AS tmp
                	ORDER BY tmp.id;";
                $q = $conn->prepare($sql);
                $q->bindParam(':did', $dialog_id, PDO::PARAM_INT);
                $q->bindParam(':o', $offset, PDO::PARAM_INT);
                $q->bindParam(':l', $limit, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetchAll(PDO::FETCH_ASSOC);
                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }

            $message_data = $res;

            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);
                $connection->send($json);
            } else {
                $request['section'] = 'getMessageList';
                $request['dialogID'] = $dialog_id;
                $request['lastMessageText'] = $dialog_data['lastMessageText'];
                $request['lastMessageDate'] = $dialog_data['lastMessageDate'];
                $request['countNewMessage'] = $dialog_data['countNewMessage'];
                $request['newMsg'] = true;
                $request['clearMsg'] = false;
                $request['data'] = $message_data;

                $json = json_encode($request);

                //$ws_connections = $users[$user_id];
                $ws_connections = $users::getConnectionsByUserID($user_id);
                foreach ($ws_connections as $ws) {
                    $ws->send($json);
                }
            }
            break;
        case 'getTyping':
            $userTo = $data['data']['userTo'];
            $dialog_id = $data['data']['dialogID'];

            $request['section'] = 'getTyping';
            $request['dialogID'] = $dialog_id;
            $json = json_encode($request);

            //if (array_key_exists($userTo, $users)) {
            if ($users::checkUserID($userTo)) {
                //$ws_connections = $users[$userTo];
                $ws_connections = $users::getConnectionsByUserID($userTo);
                foreach ($ws_connections as $ws) {
                    $ws->send($json);
                }
            }
            break;
        case 'addMessage':
            $userfrom = $data['data']['userFrom'];
            $userto = $data['data']['userTo'];
            $dialog = $data['data']['dialogID'];
            $message = $data['data']['message'];
            $err_flag = false;
            $status = 0;

            $user_ch = $userfrom; //Перепенная для проверки роли юзера

            $servername = "localhost";
            $username = "intact_user";
            $password = "0H7s5N3t";
            //Перед отправкой сообщения определяем, является ли юзер Ф
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT COUNT(*) AS flag FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $flag = (int)$res['flag'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }
            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            //Если юзер является Ф, то
            if ($flag) {
                //...переопределяем $user_ch как id оператора
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT UFO.operator_id FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $user_ch = (int)$res['operator_id'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
            }
            //Проверяем роль юзера
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT rules FROM `ph_user_attributes` where internalKey = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_ch, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);

                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);
                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $rules = (int)$res['rules'];

                $chat_check = true;

                //Если не оператор и не админ, то проверяем на предмет оплаты чата
                if ($rules != 3 and $rules != 2) {
                    $sql = 'SELECT * FROM ph_basket WHERE date_sale is not null and id_user = :uid AND type = "chat" ORDER BY date_sale DESC';
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);

                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);
                        $connection->send($json);
                        return false;
                    }
                    $chat_pay = $q->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($chat_pay)) {
                        $date_sale = $chat_pay[0]['date_sale'];
                        $day = $chat_pay[0]['day'];
                        $date = new DateTime($date_sale);
                        $date->modify('+'.$day.' days');
                        $date_check = $date->format('Y-m-d H:i:s');
                        $current_date = date('Y-m-d H:i:s');

                        if ( strtotime($date_check) < strtotime($current_date) ) {
                            $chat_check = false;
                        }
                    } else {
                        $chat_check = false;
                    }

                    //Если чат не оплачен, то проверяем кол-во использованных сообщений
                    if (!$chat_check) {
                        //Считаем кол-во бесплатных сообщений, определённых системой
                        $sql = 'SELECT `value` FROM `ph_system_settings` WHERE `key` = "free_message"';
                        $q = $conn->prepare($sql);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);

                            $request['section'] = 'ErrrorStatus';
                            $json = json_encode($request);
                            $connection->send($json);
                            return false;
                        }

                        $data = $q->fetch(PDO::FETCH_ASSOC);
                        if(isset($data['value'])){
                            $price = $data['value'];
                        } else {
                            $price = 10;
                        }
                        //Считаем кол-во сообщений, отправленных юзером
                        $sql = 'SELECT count(*) as cnt FROM `ph_chat_message` where sender_id = :uid;';
                        $q = $conn->prepare($sql);
                        $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);

                            $request['section'] = 'ErrrorStatus';
                            $json = json_encode($request);
                            $connection->send($json);
                            return false;
                        }

                        $data = $q->fetch(PDO::FETCH_ASSOC);
                        $cnt = $data['cnt'];

                        if ($price > $cnt) {
                            $chat_check = true;
                        }
                    }
                }

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }
            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }
            //Если не прошёл проверку, то возвращаем соответсвующий статус и завершаем работу
            if (!$chat_check) {
                $request['section'] = 'UnpaidChat';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            /*$flag_img = getimagesize($message);
            if ($flag_img) {
                $status = 2;
            } else {
                $flag_img = is_url_image($message);
                if ($flag_img) {
                    $status = 2;
                }
            }*/
            //Если всё ОК, продолжаем работу
            $imgExts = ["gif", "jpg", "jpeg", "png", "tiff", "tif"];

            $headers = get_headers($message, 1);
            if (strpos($headers['Content-Type'], 'image/') !== false) {
                $status = 2;
            } else {
                $urlExt = pathinfo($message, PATHINFO_EXTENSION);
                if (in_array($urlExt, $imgExts)) {
                    $status = 2;
                } else {
                    //if (@getimagesize($message)) {
                    if (exif_imagetype($message)) {
                        $status = 2;
                    }
                }
            }

            $dt = date('Y-m-d H:i:s');

            $date = date_create($dt);
            $dt_1 = date_format($date,"d.m.Y H:i:s");

            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "INSERT INTO `ph_chat_message`(`message`, `sender_id`, `dialog_id`, `status`, `date_create`) VALUES (:m, :sid, :did, :s, :dt);";
                $q = $conn->prepare($sql);
                $q->bindParam(':m', $message, PDO::PARAM_STR);
                $q->bindParam(':sid', $userfrom, PDO::PARAM_INT);
                $q->bindParam(':did', $dialog, PDO::PARAM_INT);
                $q->bindParam(':s', $status, PDO::PARAM_INT);
                $q->bindParam(':dt', $dt, PDO::PARAM_STR);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $sql = "SELECT LAST_INSERT_ID() as id FROM `ph_chat_message`";
                $q = $conn->prepare($sql);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $id = $res['id'];

                ///////////////////////////////////////////////////////////////////

                $sql = "SELECT COUNT(*) AS cnt_to FROM ph_chat_message WHERE sender_id != :uid AND view_message = 0 AND dialog_id = :did";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                $q->bindParam(':did', $dialog, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $cnt_to = $res['cnt_to'];

                /*-----------------------------------------------------------------------------*/

                $sql = "SELECT COUNT(*) AS cnt_from FROM ph_chat_message WHERE sender_id != :uid AND view_message = 0 AND dialog_id = :did";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                $q->bindParam(':did', $dialog, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $cnt_from = $res['cnt_from'];

                /*-------------------------------------Подсчёт новых сообщений-----------------------------------------------*/
                $sql = "SELECT COUNT(*) AS cnt FROM ph_chat_user_to_dialog CUD
            		JOIN ph_chat_message CM ON CUD.dialog_id = CM.dialog_id AND CM.view_message = 0 AND CM.sender_id != :uid
            		JOIN ph_users U on U.id = CM.sender_id
            		WHERE CUD.user_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $cnt = $res['cnt'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }

            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            $message_data = [
                ['senderID' => $userfrom, 'id' => $id, 'status' => $status, 'message' => $message, 'dateCreate' => $dt_1, 'view_message' => '0']
            ];

            $request['section'] = 'getMessageList';
            $request['dialogID'] = $dialog;
            $request['lastMessageText'] = $message;
            $request['lastMessageDate'] = $dt_1;
            $request['countNewMessage'] = $cnt_from;
            $request['newMsg'] = false;
            $request['clearMsg'] = false;
            $request['data'] = $message_data;
            $json = json_encode($request);

            //$ws_connections = $users[$userfrom];
            $ws_connections = $users::getConnectionsByUserID($userfrom);
            foreach ($ws_connections as $ws) {
                $ws->send($json);
            }

            $flag_send = false; // - флаг отправки сообщения на мыло. По-умолчанию false

            //Проверяем, онлайн ли юзер, и если онлайн - отправляем сообщение
            //if (array_key_exists($userto, $users)) {
            if ($users::checkUserID($userto)) {
                //$ws_connections_to = $users[$userto];
                $ws_connections_to = $users::getConnectionsByUserID($userto);

                $request['countNewMessage'] = $cnt_to;
                $request['clearMsg'] = true;
                $json = json_encode($request);
                foreach ($ws_connections_to as $ws) {
                    $ws->send($json);
                }
            } else {
                //Если не онлайн, то устанавливаем флаг отправки сообщения в true
                $flag_send = true;
            }

            //Проверяем, является ли юзер Ф или нет
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT COUNT(*) AS flag FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $flag = (int)$res['flag'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }
            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            //Если юзер является Ф, то
            if ($flag) {
                //... и он не онлайн, то
                if ($flag_send) {
                    //... формируем список Ф оператора
                    try {
                        $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $sql = "SELECT UFO.user_id
                            FROM ph_users_for_operator UFO
                            WHERE UFO.operator_id = (SELECT operator_id 
                            	FROM ph_users_for_operator
                            	WHERE user_id = :uid);";
                        $q = $conn->prepare($sql);
                        $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);
                            $err_flag = true;
                        }

                        $res = $q->fetchAll(PDO::FETCH_ASSOC);
                    } catch(PDOException $e) {
                        $conn = null;
                        LogerTXT($e->getMessage());
                        $err_flag = true;
                    }
                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }
                    //Если среди списка Ф есть кто-то онлайн, то сообщение на мыло не передаём
                    foreach($res as $r) {
                        //if (array_key_exists($r['user_id'], $users)) {
                        if ($users::checkUserID($r['user_id'])) {
                            $flag_send = false;
                        }
                    }
                }
                //...переопределяем $userto как id оператора
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT UFO.operator_id FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $userto = (int)$res['operator_id'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
                //...считаем кол-во сообщений как для оператора
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT SUM((SELECT COUNT(*) FROM ph_chat_message WHERE sender_id != UFO.user_id AND view_message = 0 AND dialog_id IN (SELECT dialog_id FROM ph_chat_user_to_dialog WHERE user_id = UFO.user_id))) AS cnt
                        FROM ph_users_for_operator UFO
                        WHERE UFO.operator_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $cnt = $res['cnt'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
            }

            //Сообщаем о новом сообщении в title-menu
            //if (array_key_exists($userto, $users)) {
            if ($users::checkUserID($userto)) {
                //$ws_connections_to = $users[$userto];
                $ws_connections_to = $users::getConnectionsByUserID($userto);
                $request = [];
                $request['section'] = 'CheckNewMessage';
                $request['cnt'] = $cnt;
                $json = json_encode($request);
                foreach ($ws_connections_to as $ws) {
                    $ws->send($json);
                }
            }

            //Проверка переменной отправки сообщения на мыло
            if ($flag_send) {
                //Проверяем разрешённости отправки сообщений на мыло для данного юзера
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT COUNT(*) AS flag FROM `ph_chat_message_to_email` WHERE user_id = :uid AND flag = 0;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $fflag = (int)$res['flag'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
                //Если отправлять можно
                if ($fflag == 0) {
                    //Добавляем инфу в ph_chat_message_to_email
                    try {
                        $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $sql = "INSERT INTO `ph_chat_message_to_email`(`user_id`) VALUES (:uid);";
                        $q = $conn->prepare($sql);
                        $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);
                            $err_flag = true;
                        }

                        $conn = null;
                    } catch(PDOException $e) {
                        $conn = null;
                        LogerTXT($e->getMessage());
                        $err_flag = true;
                    }
                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }
                    //Получаем email юзера
                    try {
                        $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $sql = "SELECT email FROM `ph_user_attributes` WHERE internalKey = :uid;";
                        $q = $conn->prepare($sql);
                        $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);
                            $err_flag = true;
                        }

                        $res = $q->fetch(PDO::FETCH_ASSOC);
                        $email = $res['email'];

                        $conn = null;
                    } catch(PDOException $e) {
                        $conn = null;
                        LogerTXT($e->getMessage());
                        $err_flag = true;
                    }
                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }
                    //Получаем имя отправителя
                    try {
                        $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $sql = "SELECT fullname FROM `ph_user_attributes` WHERE internalKey = :uid;";
                        $q = $conn->prepare($sql);
                        $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);
                            $err_flag = true;
                        }

                        $res = $q->fetch(PDO::FETCH_ASSOC);
                        $fullname = $res['fullname'];

                        $conn = null;
                    } catch(PDOException $e) {
                        $conn = null;
                        LogerTXT($e->getMessage());
                        $err_flag = true;
                    }
                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }
                    // Создаем письмо
                    try {
                        $mail = new PHPMailer();
                        $mail->SMTPDebug = 0;
                        $mail->isSMTP();
                        $mail->Host   = '172.17.0.2';  // Адрес SMTP сервера
                        $mail->SMTPAuth   = true;          // Enable SMTP authentication
                        $mail->Username   = 'support@intactshow.com';       // ваше имя пользователя (без домена и @)
                        $mail->Password   = '1qaz@WSX';    // ваш пароль
                        $mail->SMTPAutoTLS = true;
                        $mail->SMTPSecure = 'ssl';         // шифрование ssl
                        $mail->Port   = 465;               // порт подключения
                        $mail->SMTPKeepAlive = true;
                        $mail->Mailer = "smtp";
                        $mail->CharSet  = "UTF-8";
                        $mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            ]
                        ];

                        $mail->setFrom('support@intactshow.com'); // от кого (email и имя)
                        $mail->addAddress($email);  // кому (email и имя)
                        $mail->isHTML(true);
                        $mail->Subject = '=?UTF-8?B?'.base64_encode('New message').'?=';                 // тема письма
                        // html текст письма
                        $mail->Body = 'You have a new message from '.$fullname.' in the chat on the site <a href="https://intactshow.com/">IntactShow.com</a>. Please follow the link to check for new messages';
                        // Отправляем
                        if (!($mail->send())) {
                            LogerTXT($mail->ErrorInfo);
                            $err_flag = true;
                        }
                    } catch (Exception $e) {
                        LogerTXT($e->getMessage());
                        $err_flag = true;
                    }
                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }
                }
            }
            break;
        case 'clearNewMessage':
            $user_id = $data['data']['userID'];
            $dialog_id = $data['data']['dialogID'];
            $err_flag = false;

            $servername = "localhost";
            $username = "intact_user";
            $password = "0H7s5N3t";
            /*Прежде чем удалить статусы новых сообщений проверяем юзера на его принадлежность к Ф*/
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT COUNT(*) AS flag FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);

                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $flag = (int)$res['flag'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());

                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            //Если юзер является Ф
            if ($flag) {
                //Проверяем наличие новых (т.е. не прочитанных) сообщений на данном Ф и в данном диалоге
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $sql = "SELECT COUNT(*) AS flag FROM ph_chat_message WHERE sender_id != :uid AND view_message = 0 AND dialog_id = :did;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->bindParam(':did', $dialog_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);

                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
                $res = $q->fetch(PDO::FETCH_ASSOC);
                $flag_do = (int)$res['flag'];

                $conn = null;
                //Если есть новые сообщения
                if ($flag_do) {
                    //...то добавляем инфу в очередь
                    $connection_mq = new AMQPStreamConnection('localhost', 5672, 'rmuser', 'rmpassword');
                    $channel = $connection_mq->channel();

                    $channel->exchange_declare('operator_ex', 'direct');
                    $channel->queue_declare('operator_q', false, false, false, false);
                    $channel->queue_bind('operator_q', 'operator_ex', 'new_notif');

                    $dt = [];
                    $dt['userID'] = $user_id;
                    $json_mq = json_encode($dt);

                    $msg = new AMQPMessage($json_mq);
                    $channel->basic_publish($msg, 'operator_ex', 'new_notif');

                    $channel->close();
                    $connection_mq->close();

                    //... и возвращаем статус клиенту
                    $request['section'] = 'queueSucces';
                    $json = json_encode($request);

                    $connection->send($json);
                }
            }

            /*После манипуляций с очередью, выполняем основной код*/
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "UPDATE ph_chat_message SET view_message = 1 WHERE sender_id != :uid AND dialog_id = :did AND view_message = 0;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->bindParam(':did', $dialog_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $sql = "SELECT sender_id FROM ph_chat_message WHERE sender_id != :uid AND dialog_id = :did LIMIT 1;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->bindParam(':did', $dialog_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                } else {
                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $user_to = $res['sender_id'];
                }

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }

            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
            } else {
                $request['section'] = 'clearNewMessage';
                $request['dialogID'] = $dialog_id;
                $request['result'] = true;
            }

            $json = json_encode($request);

            //$ws_connections = $users[$user_id];
            $ws_connections = $users::getConnectionsByUserID($user_id);
            foreach ($ws_connections as $ws) {
                $ws->send($json);
            }

            /*Ставим отметку о прочитанности сообщений*/
            if (!$err_flag) {
                $request = [];
                $request['section'] = 'clearNewCheck';
                $json = json_encode($request);

                //if (array_key_exists($user_to, $users)) {
                if ($users::checkUserID($user_to)) {
                    //$ws_connections = $users[$user_to];
                    $ws_connections = $users::getConnectionsByUserID($user_to);
                    foreach ($ws_connections as $ws) {
                        $ws->send($json);
                    }
                }
            }

            $cnt = 0; //Если юзер не Ф, то всегда равно 0
            //Вторая проверка на Ф... Если юзер является Ф
            if ($flag) {
                //...то инициируем в переменную $user_id идентификатор оператора
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT UFO.operator_id FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $user_id = (int)$res['operator_id'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
                //...считаем кол-во сообщений как для оператора
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT SUM((SELECT COUNT(*) FROM ph_chat_message WHERE sender_id != UFO.user_id AND view_message = 0 AND dialog_id IN (SELECT dialog_id FROM ph_chat_user_to_dialog WHERE user_id = UFO.user_id))) AS cnt
                        FROM ph_users_for_operator UFO
                        WHERE UFO.operator_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $cnt = $res['cnt'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
            }


            /*Сообщаем о новом сообщении в title-menu*/
            //if (array_key_exists($user_id, $users)) {
            if ($users::checkUserID($user_id)) {
                //$ws_connections_to = $users[$user_id];
                $ws_connections_to = $users::getConnectionsByUserID($user_id);
                $request = [];
                $request['section'] = 'CheckNewMessage';
                $request['cnt'] = $cnt;
                $json = json_encode($request);
                foreach ($ws_connections_to as $ws) {
                    $ws->send($json);
                }
                //$ws_connection_to->send($json);
            }
            break;
        case 'addMessageStiker':
            $userfrom = $data['data']['userFrom'];
            $userto = $data['data']['userTo'];
            $dialog = $data['data']['dialogID'];
            $message = $data['data']['message'];
            $err_flag = false;

            $user_ch = $userfrom; //Перепенная для проверки роли юзера

            $dt = date('Y-m-d H:i:s');

            $date = date_create($dt);
            $dt_1 = date_format($date,"d.m.Y H:i:s");

            $servername = "localhost";
            $username = "intact_user";
            $password = "0H7s5N3t";
            //Перед отправкой сообщения определяем, является ли юзер Ф
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT COUNT(*) AS flag FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $flag = (int)$res['flag'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }
            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            //Если юзер является Ф, то
            if ($flag) {
                //...переопределяем $user_ch как id оператора
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT UFO.operator_id FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $user_ch = (int)$res['operator_id'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
            }
            //Проверяем роль юзера
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT rules FROM `ph_user_attributes` where internalKey = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_ch, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);

                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);
                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $rules = (int)$res['rules'];

                $chat_check = true;
                //Если не оператор и не админ, то проверяем на предмет оплаты чата
                if ($rules != 3 and $rules != 2) {
                    $sql = 'SELECT * FROM ph_basket WHERE date_sale is not null and id_user = :uid AND type = "chat" ORDER BY date_sale DESC';
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);

                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);
                        $connection->send($json);
                        return false;
                    }
                    $chat_pay = $q->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($chat_pay)) {
                        $date_sale = $chat_pay[0]['date_sale'];
                        $day = $chat_pay[0]['day'];
                        $date = new DateTime($date_sale);
                        $date->modify('+'.$day.' days');
                        $date_check = $date->format('Y-m-d H:i:s');
                        $current_date = date('Y-m-d H:i:s');

                        if ( strtotime($date_check) < strtotime($current_date) ) {
                            $chat_check = false;
                        }
                    } else {
                        $chat_check = false;
                    }

                    //Если чат не оплачен, то проверяем кол-во использованных сообщений
                    if (!$chat_check) {
                        //Считаем кол-во бесплатных сообщений, определённых системой
                        $sql = 'SELECT `value` FROM `ph_system_settings` WHERE `key` = "free_message"';
                        $q = $conn->prepare($sql);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);

                            $request['section'] = 'ErrrorStatus';
                            $json = json_encode($request);
                            $connection->send($json);
                            return false;
                        }

                        $data = $q->fetch(PDO::FETCH_ASSOC);
                        if(isset($data['value'])){
                            $price = $data['value'];
                        } else {
                            $price = 10;
                        }
                        //Считаем кол-во сообщений, отправленных юзером
                        $sql = 'SELECT count(*) as cnt FROM `ph_chat_message` where sender_id = :uid;';
                        $q = $conn->prepare($sql);
                        $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);

                            $request['section'] = 'ErrrorStatus';
                            $json = json_encode($request);
                            $connection->send($json);
                            return false;
                        }

                        $data = $q->fetch(PDO::FETCH_ASSOC);
                        $cnt = $data['cnt'];

                        if ($price > $cnt) {
                            $chat_check = true;
                        }
                    }
                }

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }
            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            //Если не прошёл проверку, то возвращаем соответсвующий статус и завершаем работу
            if (!$chat_check) {
                $request['section'] = 'UnpaidChat';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            //Если всё ОК, то продолжаем работу
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "INSERT INTO `ph_chat_message`(`message`, `sender_id`, `dialog_id`, `status`, `date_create`) VALUES (:m, :sid, :did, 1, :dt);";
                $q = $conn->prepare($sql);
                $q->bindParam(':m', $message, PDO::PARAM_STR);
                $q->bindParam(':sid', $userfrom, PDO::PARAM_INT);
                $q->bindParam(':did', $dialog, PDO::PARAM_INT);
                $q->bindParam(':dt', $dt, PDO::PARAM_STR);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $sql = "SELECT LAST_INSERT_ID() as id FROM `ph_chat_message`";
                $q = $conn->prepare($sql);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $id = $res['id'];

                ///////////////////////////////////////////////////////////////////

                $sql = "SELECT COUNT(*) AS cnt_to FROM ph_chat_message WHERE sender_id != :uid AND view_message = 0 AND dialog_id = :did";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                $q->bindParam(':did', $dialog, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $cnt_to = $res['cnt_to'];

                /*-----------------------------------------------------------------------------*/

                $sql = "SELECT COUNT(*) AS cnt_from FROM ph_chat_message WHERE sender_id != :uid AND view_message = 0 AND dialog_id = :did";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                $q->bindParam(':did', $dialog, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $cnt_from = $res['cnt_from'];

                /*-------------------------------------Подсчёт новых сообщений-----------------------------------------------*/
                $sql = "SELECT COUNT(*) AS cnt FROM ph_chat_user_to_dialog CUD
            		JOIN ph_chat_message CM ON CUD.dialog_id = CM.dialog_id AND CM.view_message = 0 AND CM.sender_id != :uid
            		JOIN ph_users U on U.id = CM.sender_id
            		WHERE CUD.user_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $cnt = $res['cnt'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }

            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            $message_data = [
                ['senderID' => $userfrom, 'id' => $id, 'status' => '1', 'message' => $message, 'dateCreate' => $dt_1, 'view_message' => '0']
            ];

            $request['section'] = 'getMessageList';
            $request['dialogID'] = $dialog;
            $request['lastMessageText'] = '(Sticker)';
            $request['lastMessageDate'] = $dt_1;
            $request['countNewMessage'] = $cnt_from;
            $request['newMsg'] = false;
            $request['clearMsg'] = false;
            $request['data'] = $message_data;
            $json = json_encode($request);

            //$ws_connections = $users[$userfrom];
            $ws_connections = $users::getConnectionsByUserID($userfrom);
            foreach ($ws_connections as $ws) {
                $ws->send($json);
            }

            $flag_send = false; // - флаг отправки сообщения на мыло. По-умолчанию false

            //Проверяем, онлайн ли юзер, и если онлайн - отправляем сообщение
            //if (array_key_exists($userto, $users)) {
            if ($users::checkUserID($userto)) {
                //$ws_connections_to = $users[$userto];
                $ws_connections_to = $users::getConnectionsByUserID($userto);

                $request['countNewMessage'] = $cnt_to;
                $request['clearMsg'] = true;
                $json = json_encode($request);
                foreach ($ws_connections_to as $ws) {
                    $ws->send($json);
                }
                //$ws_connection_to->send($json);
            } else {
                //Если не онлайн, то устанавливаем флаг отправки сообщения в true
                $flag_send = true;
            }

            //Проверяем, является ли юзер Ф или нет
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT COUNT(*) AS flag FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $flag = (int)$res['flag'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }
            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            //Если юзер является Ф, то
            if ($flag) {
                //... и он не онлайн, то
                if ($flag_send) {
                    //... формируем список Ф оператора
                    try {
                        $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $sql = "SELECT UFO.user_id
                            FROM ph_users_for_operator UFO
                            WHERE UFO.operator_id = (SELECT operator_id 
                            	FROM ph_users_for_operator
                            	WHERE user_id = :uid);";
                        $q = $conn->prepare($sql);
                        $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);
                            $err_flag = true;
                        }

                        $res = $q->fetchAll(PDO::FETCH_ASSOC);
                    } catch(PDOException $e) {
                        $conn = null;
                        LogerTXT($e->getMessage());
                        $err_flag = true;
                    }
                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }
                    //Если среди списка Ф есть кто-то онлайн, то сообщение на мыло не передаём
                    foreach($res as $r) {
                        //if (array_key_exists($r['user_id'], $users)) {
                        if ($users::checkUserID($r['user_id'])) {
                            $flag_send = false;
                        }
                    }
                }
                //...переопределяем $userto как id оператора
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT UFO.operator_id FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $userto = (int)$res['operator_id'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
                //...считаем кол-во сообщений как для оператора
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT SUM((SELECT COUNT(*) FROM ph_chat_message WHERE sender_id != UFO.user_id AND view_message = 0 AND dialog_id IN (SELECT dialog_id FROM ph_chat_user_to_dialog WHERE user_id = UFO.user_id))) AS cnt
                        FROM ph_users_for_operator UFO
                        WHERE UFO.operator_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $cnt = $res['cnt'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
            }

            //Сообщаем о новом сообщении в title-menu
            //if (array_key_exists($userto, $users)) {
            if ($users::checkUserID($userto)) {
                //$ws_connections_to = $users[$userto];
                $ws_connections_to = $users::getConnectionsByUserID($userto);
                $request = [];
                $request['section'] = 'CheckNewMessage';
                $request['cnt'] = $cnt;
                $json = json_encode($request);
                foreach ($ws_connections_to as $ws) {
                    $ws->send($json);
                }
            }

            //Проверка переменной отправки сообщения на мыло
            if ($flag_send) {
                //Проверяем разрешённости отправки сообщений на мыло для данного юзера
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT COUNT(*) AS flag FROM `ph_chat_message_to_email` WHERE user_id = :uid AND flag = 0;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $fflag = (int)$res['flag'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
                //Если отправлять можно
                if ($fflag == 0) {
                    //Добавляем инфу в ph_chat_message_to_email
                    try {
                        $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $sql = "INSERT INTO `ph_chat_message_to_email`(`user_id`) VALUES (:uid);";
                        $q = $conn->prepare($sql);
                        $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);
                            $err_flag = true;
                        }

                        $conn = null;
                    } catch(PDOException $e) {
                        $conn = null;
                        LogerTXT($e->getMessage());
                        $err_flag = true;
                    }
                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }
                    //Получаем email юзера
                    try {
                        $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $sql = "SELECT email FROM `ph_user_attributes` WHERE internalKey = :uid;";
                        $q = $conn->prepare($sql);
                        $q->bindParam(':uid', $userto, PDO::PARAM_INT);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);
                            $err_flag = true;
                        }

                        $res = $q->fetch(PDO::FETCH_ASSOC);
                        $email = $res['email'];

                        $conn = null;
                    } catch(PDOException $e) {
                        $conn = null;
                        LogerTXT($e->getMessage());
                        $err_flag = true;
                    }
                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }
                    //Получаем имя отправителя
                    try {
                        $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $sql = "SELECT fullname FROM `ph_user_attributes` WHERE internalKey = :uid;";
                        $q = $conn->prepare($sql);
                        $q->bindParam(':uid', $userfrom, PDO::PARAM_INT);
                        $q->execute();
                        $err = $q->errorInfo();
                        if ($err[2]) {
                            $conn = null;
                            LogerTXT($err[2]);
                            $err_flag = true;
                        }

                        $res = $q->fetch(PDO::FETCH_ASSOC);
                        $fullname = $res['fullname'];

                        $conn = null;
                    } catch(PDOException $e) {
                        $conn = null;
                        LogerTXT($e->getMessage());
                        $err_flag = true;
                    }
                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }
                    // Создаем письмо
                    try {
                        $mail = new PHPMailer();
                        $mail->SMTPDebug = 0;
                        $mail->isSMTP();
                        $mail->Host   = '172.17.0.2';  // Адрес SMTP сервера
                        $mail->SMTPAuth   = true;          // Enable SMTP authentication
                        $mail->Username   = 'support@intactshow.com';       // ваше имя пользователя (без домена и @)
                        $mail->Password   = '1qaz@WSX';    // ваш пароль
                        $mail->SMTPAutoTLS = true;
                        $mail->SMTPSecure = 'ssl';         // шифрование ssl
                        $mail->Port   = 465;               // порт подключения
                        $mail->SMTPKeepAlive = true;
                        $mail->Mailer = "smtp";
                        $mail->CharSet  = "UTF-8";
                        $mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            ]
                        ];

                        $mail->setFrom('support@intactshow.com'); // от кого (email и имя)
                        $mail->addAddress($email);  // кому (email и имя)
                        $mail->isHTML(true);
                        $mail->Subject = '=?UTF-8?B?'.base64_encode('New message').'?=';                 // тема письма
                        // html текст письма
                        $mail->Body = 'You have a new message from '.$fullname.' in the chat on the site <a href="https://intactshow.com/">IntactShow.com</a>. Please follow the link to check for new messages';
                        // Отправляем
                        if (!($mail->send())) {
                            LogerTXT($mail->ErrorInfo);
                            $err_flag = true;
                        }
                    } catch (Exception $e) {
                        LogerTXT($e->getMessage());
                        $err_flag = true;
                    }
                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }
                }
            }
            break;
        case 'ChangeUserByOperator':
            $user = $data['data']['userID'];

            //удаляем параметр юзера
            //unset($users[$user]);
            $users::removeUser($user);

            /*Отсылаем всем, что типа челик не онлайн*/
            $request['section'] = 'userOffline';
            $request['userID'] = $user;
            $json = json_encode($request);
            foreach($wsWorker->connections as $clientConnection) {
                $clientConnection->send($json);
            }

            break;
        case 'CheckNewMessage':
            $user = $data['data']['userID'];

            $servername = "localhost";
            $username = "intact_user";
            $password = "0H7s5N3t";
            //Проверяем, является ли юзер оператором или нет
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT COUNT(*) AS flag FROM `ph_users_for_operator` UFO WHERE UFO.operator_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $flag = (int)$res['flag'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }
            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            //Если юзер является оператором, то
            if ($flag) {
                //...считаем кол-во сообщений как для оператора
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT SUM((SELECT COUNT(*) FROM ph_chat_message WHERE sender_id != UFO.user_id AND view_message = 0 AND dialog_id IN (SELECT dialog_id FROM ph_chat_user_to_dialog WHERE user_id = UFO.user_id))) AS cnt
                        FROM ph_users_for_operator UFO
                        WHERE UFO.operator_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $user, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $cnt = $res['cnt'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
            } else {
                //Если не Ф, то считаем сообщения как для конкретного юзера
                try {
                    $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $sql = "SELECT COUNT(*) AS cnt FROM ph_chat_user_to_dialog CUD
                        JOIN ph_chat_message CM ON CUD.dialog_id = CM.dialog_id AND CM.view_message = 0 AND CM.sender_id != :uid
                        JOIN ph_users U on U.id = CM.sender_id
                        WHERE CUD.user_id = :uid;";
                    $q = $conn->prepare($sql);
                    $q->bindParam(':uid', $user, PDO::PARAM_INT);
                    $q->execute();
                    $err = $q->errorInfo();
                    if ($err[2]) {
                        $conn = null;
                        LogerTXT($err[2]);
                        $err_flag = true;
                    }

                    if ($err_flag) {
                        $request['section'] = 'ErrrorStatus';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }

                    $res = $q->fetch(PDO::FETCH_ASSOC);
                    $cnt = $res['cnt'];

                    $conn = null;
                } catch(PDOException $e) {
                    $conn = null;
                    LogerTXT($e->getMessage());
                    $err_flag = true;
                }
                if ($err_flag) {
                    $request['section'] = 'ErrrorStatus';
                    $json = json_encode($request);

                    $connection->send($json);
                    return false;
                }
            }

            $request['section'] = 'CheckNewMessage';
            $request['cnt'] = $cnt;
            $json = json_encode($request);

            //$ws_connections = $users[$user];
            $ws_connections = $users::getConnectionsByUserID($user);
            foreach ($ws_connections as $ws) {
                $ws->send($json);
            }
            break;
        case 'deleteDialog': //Удаление диалога
            $dialog_id = $data['data']['dialogID'];
            $user_to = $data['data']['userTo'];

            $request['section'] = 'deleteDialog';
            $request['dialog'] = $dialog_id;
            $json = json_encode($request);

            //Отправляем юзеру
            //if (array_key_exists($user_to, $users)) {
            if ($users::checkUserID($user_to)) {
                //$ws_connections_to = $users[$user_to];
                $ws_connections_to = $users::getConnectionsByUserID($user_to);

                foreach ($ws_connections_to as $ws) {
                    $ws->send($json);
                }
            }

            break;
        case 'operatorQueue': //Принимает значения с клиента и передаёт всё в очередь
            $user_id = $data['data']['userID'];
            $err_flag = false;

            //Проверяем, является ли юзер Ф или нет
            $servername = "localhost";
            $username = "intact_user";
            $password = "0H7s5N3t";
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT COUNT(*) AS flag FROM `ph_users_for_operator` UFO WHERE UFO.user_id = :uid;";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $flag = (int)$res['flag'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                $err_flag = true;
            }
            if ($err_flag) {
                $request['section'] = 'ErrrorStatus';
                $json = json_encode($request);

                $connection->send($json);
                return false;
            }

            //Если юзер является Ф
            if ($flag) {
                //...то добавляем инфу в очередь
                $connection_mq = new AMQPStreamConnection('localhost', 5672, 'rmuser', 'rmpassword');
                $channel = $connection_mq->channel();

                $channel->exchange_declare('operator_ex', 'direct');
                $channel->queue_declare('operator_q', false, false, false, false);
                $channel->queue_bind('operator_q', 'operator_ex', 'new_notif');

                $dt = [];
                $dt['userID'] = $user_id;
                $json_mq = json_encode($dt);

                $msg = new AMQPMessage($json_mq);
                $channel->basic_publish($msg, 'operator_ex', 'new_notif');

                $channel->close();
                $connection_mq->close();

                //... и возвращаем статус клиенту
                $request['section'] = 'queueSucces';
                $json = json_encode($request);

                $connection->send($json);
            }
            break;
        case 'operatorReciever': //принимет значение с очереди. Проверяет есть ли новые сообщения у Ф и передаёт результат клиенту Оператора (если он онлайн, конечно)
            $user_id = $data['data']['userID'];
            $err_flag = false;

            $servername = "localhost";
            $username = "intact_user";
            $password = "0H7s5N3t";
            //Проверяем есть ли новое сообщение на данном Ф
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT COUNT(*) AS cnt FROM ph_chat_message WHERE sender_id != :uid AND view_message = 0 AND dialog_id IN (SELECT dialog_id FROM ph_chat_user_to_dialog WHERE user_id = :uid);";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    //$err_flag = true;
                }

                $res = $q->fetch(PDO::FETCH_ASSOC);
                $cnt = $res['cnt'];

                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                //$err_flag = true;
            }
            if ($err_flag) {
                //Тут пока что так, тк обычный способ пеердачи сообщения об ошибке не сработает, ибо клиент - другой ws-сервер и коннект идёт с ним (1)
                return false;
            }

            //Составляем список Ф у оператора
            try {
                $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $sql = "SELECT UFO.user_id
        			FROM ph_users_for_operator UFO
        			WHERE UFO.operator_id = (SELECT operator_id 
        				FROM ph_users_for_operator
        				WHERE user_id = :uid);";
                $q = $conn->prepare($sql);
                $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
                $q->execute();
                $err = $q->errorInfo();
                if ($err[2]) {
                    $conn = null;
                    LogerTXT($err[2]);
                    $err_flag = true;
                }

                $res = $q->fetchAll(PDO::FETCH_ASSOC);
                $conn = null;
            } catch(PDOException $e) {
                $conn = null;
                LogerTXT($e->getMessage());
                //$err_flag = true;
            }
            if ($err_flag) {
                //Тут тоже, что и в (1)
                return false;
            }

            //Среди списка Ф ищем те, что онлайн, и передаём им оповещения. PS:в идеале должен быть онлайн только 1 Ф (но мы-то знаем этих деб.. юзеров)
            foreach($res as $r) {
                //if (array_key_exists($r['user_id'], $users)) {
                if ($users::checkUserID($r['user_id'])) {
                    $request = [];
                    $request['section'] = 'UpdateSelectUser';
                    $request['userID'] = $user_id;
                    $request['cnt'] = $cnt;

                    $json = json_encode($request);
                    //$ws_connections = $users[$r['user_id']];
                    $ws_connections = $users::getConnectionsByUserID($r['user_id']);
                    foreach ($ws_connections as $ws) {
                        $ws->send($json);
                    }
                }
            }

            break;
        default:
            LogerTXT('Неизвестная операция. $data: '.print_r($data, true));
            break;
    }
};

$wsWorker->onClose = function($connection) use (&$users, $wsWorker) {
    $request = [];

    //удаляем параметр при отключении юзера
    /*$user = array_search($connection, $users);
    unset($users[$user]);*/
    /*$user_id = 0;
    foreach ($users as $k => $u) {
        if (array_search($connection, $u) !== FALSE) {
            $user = array_search($connection, $u);
            unset($users[$k][$user]);

            if (!count($users[$k])) {
                unset($users[$k]);
                $user_id = $k;
            }
        }
    }*/

    $user_id = $users::removeUserConnect($connection);

    /*Отсылаем всем, что типа челик не онлайн, тем кто сейчас онлайн и с кем у него есть диалог, если все его коннекты удалены из массива*/
    if ($user_id) {
        $servername = "localhost";
        $username = "intact_user";
        $password = "0H7s5N3t";
        try {
            $conn = new PDO("mysql:host=$servername;dbname=intact_db", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $sql = "SELECT user_id FROM ph_chat_user_to_dialog CUD WHERE CUD.user_id != :uid AND CUD.dialog_id in (
            	SELECT dialog_id FROM ph_chat_user_to_dialog
            	WHERE user_id = :uid
            );";
            $q = $conn->prepare($sql);
            $q->bindParam(':uid', $user_id, PDO::PARAM_INT);
            $q->execute();
            $err = $q->errorInfo();
            if ($err[2]) {
                $conn = null;
                LogerTXT($err[2]);
            }

            $usrs = $q->fetchAll(PDO::FETCH_ASSOC);

            $conn = null;
        } catch(PDOException $e) {
            $conn = null;
            LogerTXT($e->getMessage());
        }

        $request['section'] = 'userOffline';
        $request['userID'] = $user_id;
        $json = json_encode($request);
        /*foreach($wsWorker->connections as $clientConnection) {
    		$clientConnection->send($json);
    	}*/
        foreach ($usrs as $us) {
            $usr_to = $us['user_id'];
            //if (array_key_exists($usr_to, $users)) {
            if ($users::checkUserID($usr_to)) {
                //$ws_connections = $users[$usr_to];
                $ws_connections = $users::getConnectionsByUserID($usr_to);
                foreach ($ws_connections as $ws) {
                    $ws->send($json);
                }
            }
        }
    }
};

Worker::runAll();