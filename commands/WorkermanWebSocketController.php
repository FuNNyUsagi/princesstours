<?php
/**
 * список сервисов для работы WorkmanWebSocket
 */

namespace app\commands;

use Yii;
use Workerman\Worker;
use yii\console\Controller;
use yii\db\Exception;
use yii\helpers\Console;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPMailer\PHPMailer\PHPMailer;
use websoket\chat\Users;
use yii\log\Logger;

require_once __DIR__ . '/../chat/autoloader_chat.php';

/**
 *
 * Непосредственно сам WorkermanWebSocket
 *
 */

class WorkermanWebSocketController extends Controller
{
    public $send;
    public $daemon;
    public $gracefully;

    public $config = [];
    private $ip = '0.0.0.0';
    private $port = '4369';

    private function isImage($url)
    {
        $params = array('http' => array(
            'method' => 'HEAD'
        ));
        $ctx = stream_context_create($params);
        $fp = @fopen($url, 'rb', false, $ctx);
        if (!$fp)
            return false;  // Problem with url

        $meta = stream_get_meta_data($fp);
        if ($meta === false)
        {
            fclose($fp);
            return false;  // Problem reading data from url
        }

        $wrapper_data = $meta["wrapper_data"];
        if(is_array($wrapper_data)){
            foreach(array_keys($wrapper_data) as $hh){
                if (substr($wrapper_data[$hh], 0, 19) == "Content-Type: image") // strlen("Content-Type: image") == 19
                {
                    fclose($fp);
                    return true;
                }
            }
        }

        fclose($fp);
        return false;
    }

    private function sendErrrorStatus($conn, $err)
    {
        Yii::error($err);
        Yii::getLogger()->flush(true);

        $req = [];
        $req['section'] = 'ErrrorStatus';

        $json = json_encode($req);
        $conn->send($json);
    }

    public function options($actionID)
    {
        return ['send', 'daemon', 'gracefully'];
    }

    public function optionAliases()
    {
        return [
            's' => 'send',
            'd' => 'daemon',
            'g' => 'gracefully',
        ];
    }

    public function actionIndex()
    {
        if ('start' == $this->send) {
            try {
                $this->start($this->daemon);
            } catch (\Exception $e) {
                $this->stderr($e->getMessage() . "\n", Console::FG_RED);
            }
        } else if ('stop' == $this->send) {
            $this->stop();
        } else if ('restart' == $this->send) {
            $this->restart();
        } else if ('reload' == $this->send) {
            $this->reload();
        } else if ('status' == $this->send) {
            $this->status();
        } else if ('connections' == $this->send) {
            $this->connections();
        }
    }

    public function initWorker()
    {
        //Инициализируем класс-реестр с юзерами
        $users = new Users();

        $ip = isset($this->config['ip']) ? $this->config['ip'] : $this->ip;
        $port = isset($this->config['port']) ? $this->config['port'] : $this->port;

        Worker::$pidFile = '/var/run/WorkermanWebSocket.pid';

        $wsWorker = new Worker("websocket://{$ip}:{$port}");

        $wsWorker->count = 1;
        $wsWorker->user = 'www-data';
        $wsWorker->group = 'www-data';
        $wsWorker->reloadable = true;

        /*$wsWorker->onConnect = function ($connection) {
            echo "New connection\n";
        };*/

        $wsWorker->onMessage = function ($connection, $data) use (&$users, $wsWorker) {
            if (!(Yii::$app->db->isActive)) {
                Yii::$app->db->open();
            }

            $data = json_decode($data, true);
            $request = [];

            switch ($data['section']) {
                case 'authorization':
                    $user_id = $data['data']['userID'];
                    $pswd = $data['data']['identif'];

                    $users::setNewConnectByUserID($user_id, $connection);
                    try {
                        $res = Yii::$app->db->createCommand('SELECT guest_is_user(:uid, :pswd) AS flag;')
                            ->bindValue(':pswd', $pswd)
                            ->bindValue(':uid', $user_id)
                            ->queryOne();

                        if ($res) {
                            $cnt = $res['flag'];
                        } else {
                            throw new Exception('Not response from "guest_is_user"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    try {
                        $usrs = Yii::$app->db->createCommand('CALL get_userid_list(:uid);')
                            ->bindValue(':uid', $user_id)
                            ->queryAll();

                        /*if (empty($usrs)) {
                            throw new Exception('Not response from "get_userid_list"');
                        }*/
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
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
                            if ($users::checkUserID($usr_to)) {
                                $ws_connections = $users::getConnectionsByUserID($usr_to);
                                foreach ($ws_connections as $ws) {
                                    $ws->send($json);
                                }
                            }
                        }
                    }

                    //Проверяем, является ли юзер Ф или нет
                    try {
                        $res = Yii::$app->db->createCommand('SELECT user_is_twink(:uid) AS flag;')
                            ->bindValue(':uid', $user_id)
                            ->queryOne();

                        if ($res) {
                            $flag = (int)$res['flag'];
                        } else {
                            throw new Exception('Not response from "user_is_twink"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    //Если юзер является Ф, то
                    if ($flag) {
                        //...переопределяем $user_id как id оператора
                        try {
                            $res = Yii::$app->db->createCommand('SELECT get_operator_id(:uid) AS operator_id;')
                                ->bindValue(':uid', $user_id)
                                ->queryOne();

                            if ($res) {
                                $user_id = (int)$res['operator_id'];
                            } else {
                                throw new Exception('Not response from "get_operator_id"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }
                    }

                    //Обнуляем статусы таблицы ph_chat_message_to_email для данного юзера
                    try {
                        Yii::$app->db->createCommand('CALL reset_email_status_for_user(:uid);')
                            ->bindValue(':uid', $user_id)
                            ->execute();
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }
                    break;
                case 'getDialogList':
                    $user_id = $data['data']['userID']; //id юзера. Это мб id реального юзера либо Ф

                    /*Отправляем список диалогов как простому юзеру*/
                    try {
                        $res = Yii::$app->db->createCommand('CALL get_dialog_list(:uid);')
                            ->bindValue(':uid', $user_id)
                            ->queryAll();

                        /*if (empty($res)) {
                            throw new Exception('Not response from "get_dialog_list"');
                        }*/
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    $full_res = [];
                    foreach($res as $r) {
                        if ($users::checkUserID($r['userToID'])) {
                            $r['online'] = true;
                        } else {
                            $r['online'] = false;
                        }
                        $full_res[] = $r;
                    }

                    $request['section'] = 'setDialogList';
                    $request['data'] = $full_res;
                    $json = json_encode($request);

                    $ws_connections = $users::getConnectionsByUserID($user_id);
                    foreach ($ws_connections as $ws) {
                        $ws->send($json);
                    }

                    /*Отправляем оповещение как для оператора, если на каком-либо Ф есть непрочитанное сообщение*/
                    $flag_new = false;
                    try {
                        /*Проверяем есть ли новое сообщение на к-л Ф*/
                        $res = Yii::$app->db->createCommand('SELECT check_msg_operator(:uid) AS flag;')
                            ->bindValue(':uid', $user_id)
                            ->queryOne();

                        if ($res) {
                            $flag_ch = (int)$res['flag'];
                        } else {
                            throw new Exception('Not response from "check_msg_operator"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    /*Если не 0, то составляем список Ф оператора*/
                    if ($flag_ch != 0) {
                        $flag_new = true;

                        try {
                            $res = Yii::$app->db->createCommand('CALL get_operator_list(:uid);')
                                ->bindValue(':uid', $user_id)
                                ->queryAll();

                            if (empty($res)) {
                                throw new Exception('Not response from "get_operator_list"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }
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
                            if ($users::checkUserID($r['user_id'])) {
                                $request = [];
                                $request['section'] = 'newMsgForOperator';
                                $request['data'] = $user_f;

                                $json = json_encode($request);
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

                    try {
                        $user_to = Yii::$app->db->createCommand('SELECT sender_id FROM ph_chat_message WHERE sender_id != :uid AND dialog_id = :did;')
                            ->bindValue(':uid', $user_id)
                            ->bindValue(':did', $dialog_id)
                            ->queryOne();
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, $ex->getMessage());
                        return false;
                    }

                    $online = false;
                    if ($user_to) {
                        if ($users::checkUserID($user_to['sender_id'])) {
                            $online = true;
                        }
                    }

                    try {
                        $res = Yii::$app->db->createCommand('CALL get_message_list(:did, :o, :l);')
                            ->bindValue(':did', $dialog_id)
                            ->bindValue(':o', $offset)
                            ->bindValue(':l', $limit)
                            ->queryAll();

                        /*if (empty($res)) {
                            throw new Exception('Not response from "get_message_list"');
                        }*/
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    $msg_dt = [];
                    /*Группируем сообщения по дате*/
                    foreach ($res as $r) {
                        $msg_dt[$r['dateCreate']][] = $r;
                    }

                    $request['section'] = 'getMessageList';
                    $request['dialogID'] = $dialog_id;
                    $request['online'] = $online;
                    $request['data'] = $msg_dt;

                    $json = json_encode($request);

                    $ws_connections = $users::getConnectionsByUserID($user_id);
                    foreach ($ws_connections as $ws) {
                        $ws->send($json);
                    }
                    break;
                case 'getTyping':
                    $userTo = $data['data']['userTo'];
                    $dialog_id = $data['data']['dialogID'];

                    $request['section'] = 'getTyping';
                    $request['dialogID'] = $dialog_id;
                    $json = json_encode($request);

                    if ($users::checkUserID($userTo)) {
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
                    $status = $data['data']['status'];

                    $user_ch = $userfrom; //Переменная для проверки роли юзера

                    //Перед отправкой сообщения определяем, является ли юзер, отправляющий сообщение, Ф
                    try {
                        $res = Yii::$app->db->createCommand('SELECT user_is_twink(:uid) AS flag;')
                            ->bindValue(':uid', $userfrom)
                            ->queryOne();

                        if ($res) {
                            $flag = (int)$res['flag'];
                        } else {
                            throw new Exception('Not response from "user_is_twink"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    //Если юзер является Ф, то
                    if ($flag) {
                        //...переопределяем $user_ch как id оператора
                        try {
                            $res = Yii::$app->db->createCommand('SELECT get_operator_id(:uid) AS operator_id;')
                                ->bindValue(':uid', $userfrom)
                                ->queryOne();

                            if ($res) {
                                $user_ch = (int)$res['operator_id'];
                            } else {
                                throw new Exception('Not response from "get_operator_id"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }
                    }
                    //Проверяем роль юзера
                    try {
                        $res = Yii::$app->db->createCommand('SELECT get_users_rules(:uid) AS rules;')
                            ->bindValue(':uid', $user_ch)
                            ->queryOne();

                        if ($res) {
                            $rules = (int)$res['rules'];
                        } else {
                            throw new Exception('Not response from "get_users_rules"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    $chat_check = true;
                    //Если не оператор и не админ, то проверяем на предмет оплаты чата
                    if ($rules != 3 and $rules != 2) {
                        try {
                            $chat_pay = Yii::$app->db->createCommand('CALL get_chat_pay_list(:uid);')
                                ->bindValue(':uid', $userfrom)
                                ->queryOne();

                            if ($chat_pay) {
                                $date_sale = $chat_pay['date_sale'];
                                $day = $chat_pay['day'];
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
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }

                        //Если чат не оплачен, то проверяем кол-во использованных сообщений
                        if (!$chat_check) {
                            try {
                                //Считаем кол-во бесплатных сообщений, определённых системой
                                $dtas = Yii::$app->db->createCommand('SELECT get_amount_free_msg() as value;')
                                    ->queryOne();

                                $price = (isset($dtas['value'])) ? $dtas['value'] : 10;

                                //Считаем кол-во сообщений, отправленных юзером
                                $dtas = Yii::$app->db->createCommand('SELECT get_msg_send_by_user(:uid) as cnt;')
                                    ->bindValue(':uid', $userfrom)
                                    ->queryOne();

                                if ($dtas) {
                                    $cnt = (int)$dtas['cnt'];
                                } else {
                                    throw new Exception('Not response from "get_msg_send_by_user"');
                                }

                                if ($price > $cnt) {
                                    $chat_check = true;
                                }
                            } catch (Exception $ex) {
                                $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                                return false;
                            }
                        }
                    }

                    //Если не прошёл проверку, то возвращаем соответствующий статус и завершаем работу
                    if (!$chat_check) {
                        $request['section'] = 'UnpaidChat';
                        $json = json_encode($request);

                        $connection->send($json);
                        return false;
                    }

                    //Проверяем, на наличие url-ссылки на картинку в сообщении
                    if ($status == 0) {
                        if ($this->isImage($message)) {
                            $status = 2;
                        }
                    }

                    $dt = date('Y-m-d H:i:s');
                    $date = date_create($dt);
                    $tm = date_format($date,"H:i");

                    //Шаблон-заглушка, если отправлен стикер или картинка
                    $msg_return = $message;
                    switch ($status) {
                        case 1:
                            $msg_return = '(Sticker)';
                            break;
                        case 2:
                            $msg_return = '(Picture)';
                            break;
                    }

                    //Добавляем сообщение
                    try {
                        $res = Yii::$app->db->createCommand('SELECT add_new_msg(:m, :sid, :did, :s, :dt) as id;')
                            ->bindValue(':m', $message)
                            ->bindValue(':sid', $userfrom)
                            ->bindValue(':did', $dialog)
                            ->bindValue(':s', $status)
                            ->bindValue(':dt', $dt)
                            ->queryOne();

                        if ($res) {
                            $id = (int)$res['id'];
                        } else {
                            throw new Exception('Not response from "add_new_msg"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    //Считаем количество сообщений отправителя и получателя
                    try {
                        $res = Yii::$app->db->createCommand('CALL get_cnt_to_and_from(:uid_to, :uid_from, :did);')
                            ->bindValue(':uid_to', $userto)
                            ->bindValue(':uid_from', $userfrom)
                            ->bindValue(':did', $dialog)
                            ->queryOne();

                        if ($res) {
                            $cnt_to = $res['cnt_to'];
                            $cnt_from = $res['cnt_from'];
                        } else {
                            throw new Exception('Not response from "get_cnt_to_and_from"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    //Подсчёт новых сообщений у получателя
                    try {
                        $res = Yii::$app->db->createCommand('SELECT get_cnt_new_msg(:uid) AS cnt;')
                            ->bindValue(':uid', $userto)
                            ->queryOne();

                        if ($res) {
                            $cnt = $res['cnt'];
                        } else {
                            throw new Exception('Not response from "get_cnt_new_msg"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    $message_data['Today'][] = ['senderID' => $userfrom, 'id' => $id, 'status' => $status, 'message' => $message, 'timeCreate' => $tm, 'view_message' => '0'];

                    /*Отправляется в сам мессенджер*/
                    $request['section'] = 'addMessage';
                    $request['dialogID'] = $dialog;
                    $request['clearMsg'] = false;
                    $request['data'] = $message_data;
                    $json = json_encode($request);

                    $ws_connections = $users::getConnectionsByUserID($userfrom);
                    foreach ($ws_connections as $ws) {
                        $ws->send($json);
                    }

                    /*Отправляется в меню выбора диалога*/
                    $request_m = [];
                    $request_m['section'] = 'addMessageMain';
                    $request_m['dialogID'] = $dialog;
                    $request_m['lastMessageText'] = 'You: '.$msg_return;
                    $request_m['lastMessageTime'] = $tm;
                    $request_m['countNewMessage'] = $cnt_from;
                    $json = json_encode($request_m);

                    foreach ($ws_connections as $ws) {
                        $ws->send($json);
                    }

                    $flag_send = false; // - флаг отправки сообщения на мыло. По-умолчанию false

                    //Проверяем, онлайн ли юзер, и если онлайн - отправляем сообщение
                    if ($users::checkUserID($userto)) {
                        $ws_connections_to = $users::getConnectionsByUserID($userto);

                        /*Отправляется в сам мессенджер*/
                        $request['clearMsg'] = true;
                        $json = json_encode($request);
                        foreach ($ws_connections_to as $ws) {
                            $ws->send($json);
                        }

                        /*Отправляется в меню выбора диалога*/
                        $request_m['lastMessageText'] = $msg_return;
                        $request_m['countNewMessage'] = $cnt_to;
                        $json = json_encode($request_m);
                        foreach ($ws_connections_to as $ws) {
                            $ws->send($json);
                        }
                    } else {
                        //Если не онлайн, то устанавливаем флаг отправки сообщения в true
                        $flag_send = true;
                    }

                    //Проверяем, является ли юзер, кому отправляют сообщение, Ф или нет
                    try {
                        $res = Yii::$app->db->createCommand('SELECT user_is_twink(:uid) AS flag;')
                            ->bindValue(':uid', $userto)
                            ->queryOne();

                        if ($res) {
                            $flag = (int)$res['flag'];
                        } else {
                            throw new Exception('Not response from "user_is_twink"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    //Если является Ф, то
                    if ($flag) {
                        //... и он не онлайн, то
                        if ($flag_send) {
                            //... формируем список Ф оператора
                            try {
                                $res = Yii::$app->db->createCommand('CALL get_all_operator_list(:uid);')
                                    ->bindValue(':uid', $userto)
                                    ->queryAll();

                                if (empty($res)) {
                                    throw new Exception('Not response from "get_all_operator_list"');
                                }
                            } catch (Exception $ex) {
                                $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                                return false;
                            }
                            //Если среди списка Ф есть кто-то онлайн, то сообщение на мыло не передаём
                            foreach($res as $r) {
                                if ($users::checkUserID($r['user_id'])) {
                                    $flag_send = false;
                                }
                            }
                        }

                        //...переопределяем $userto как id оператора
                        try {
                            $res = Yii::$app->db->createCommand('SELECT get_operator_id(:uid) AS operator_id;')
                                ->bindValue(':uid', $userto)
                                ->queryOne();

                            if ($res) {
                                $userto = (int)$res['operator_id'];
                            } else {
                                throw new Exception('Not response from "get_operator_id"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }

                        //...считаем кол-во сообщений как для оператора
                        try {
                            $res = Yii::$app->db->createCommand('SELECT get_cnt_msg_by_operator(:uid) AS cnt;')
                                ->bindValue(':uid', $userto)
                                ->queryOne();

                            if ($res) {
                                $cnt = (int)$res['cnt'];
                            } else {
                                throw new Exception('Not response from "get_cnt_msg_by_operator"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }
                    }

                    //Сообщаем о новом сообщении в title-menu
                    if ($users::checkUserID($userto)) {
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
                            $res = Yii::$app->db->createCommand('SELECT check_send_mail(:uid) AS flag;')
                                ->bindValue(':uid', $userto)
                                ->queryOne();

                            if ($res) {
                                $fflag = (int)$res['flag'];
                            } else {
                                throw new Exception('Not response from "check_send_mail"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }

                        //Если отправлять можно
                        if ($fflag == 0) {
                            //Добавляем инфу в ph_chat_message_to_email
                            try {
                                Yii::$app->db->createCommand('CALL add_new_email_to_send(:uid);')
                                    ->bindValue(':uid', $userto)
                                    ->execute();
                            } catch (Exception $ex) {
                                $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                                return false;
                            }

                            //Получаем email юзера и имя отправителя
                            try {
                                $res = Yii::$app->db->createCommand('CALL get_email_and_fullname(:uid_to, :uid_from);')
                                    ->bindValue(':uid_to', $userto)
                                    ->bindValue(':uid_from', $userfrom)
                                    ->queryOne();

                                if ($res) {
                                    $email = $res['email'];
                                    $fullname = $res['fullname'];
                                } else {
                                    throw new Exception('Not response from "get_email_and_fullname"');
                                }
                            } catch (Exception $ex) {
                                $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
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
                                $mail->Body = 'You have a new message from '.$fullname.' in the chat on the site <a href="https://shuterevents.com">Shuterevents.com</a>. Please follow the link to check for new messages';
                                // Отправляем
                                if (!($mail->send())) {
                                    throw new Exception($mail->ErrorInfo);
                                }
                            } catch (Exception $e) {
                                $this->sendErrrorStatus($connection, $e->getMessage());
                                return false;
                            }
                        }
                    }
                    break;
                case 'clearNewMessage':
                    $user_id = $data['data']['userID'];
                    $dialog_id = $data['data']['dialogID'];

                    /*Прежде чем удалить статусы новых сообщений проверяем юзера на его принадлежность к Ф*/
                    try {
                        $res = Yii::$app->db->createCommand('SELECT user_is_twink(:uid) AS flag;')
                            ->bindValue(':uid', $user_id)
                            ->queryOne();

                        if ($res) {
                            $flag = (int)$res['flag'];
                        } else {
                            throw new Exception('Not response from "user_is_twink"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    //Если юзер является Ф
                    if ($flag) {
                        //Проверяем наличие новых (т.е. не прочитанных) сообщений на данном Ф и в данном диалоге
                        try {
                            $res = Yii::$app->db->createCommand('SELECT check_new_msg(:uid, :did) AS flag;')
                                ->bindValue(':uid', $user_id)
                                ->bindValue(':did', $dialog_id)
                                ->queryOne();

                            if ($res) {
                                $flag_do = (int)$res['flag'];
                            } else {
                                throw new Exception('Not response from "check_new_msg"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }
                        //Если есть новые сообщения
                        if ($flag_do) {
                            //...то добавляем инфу в очередь - это сделано для того, чтобы обновить инфу в селекте выбора Ф
                            $connection_mq = new AMQPStreamConnection('localhost', 5672, 'rmuser', 'rmpassword');
                            $channel = $connection_mq->channel();

                            $channel->exchange_declare('operator_ex_pt', 'direct');
                            $channel->queue_declare('operator_q_pt', false, false, false, false);
                            $channel->queue_bind('operator_q_pt', 'operator_ex_pt', 'new_notif');

                            $dt = [];
                            $dt['userID'] = $user_id;
                            $json_mq = json_encode($dt);

                            $msg = new AMQPMessage($json_mq);
                            $channel->basic_publish($msg, 'operator_ex_pt', 'new_notif');

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
                        $res = Yii::$app->db->createCommand('CALL mark_as_read_and_get_sender(:uid, :did);')
                            ->bindValue(':uid', $user_id)
                            ->bindValue(':did', $dialog_id)
                            ->queryOne();

                        if ($res) {
                            $user_to = $res['sender_id'];
                        } else {
                            throw new Exception('Not response from "mark_as_read_and_get_sender('.$user_id.', '.$dialog_id.')"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    $request['section'] = 'clearNewMessage';
                    $request['dialogID'] = $dialog_id;
                    $request['result'] = true;

                    $json = json_encode($request);

                    $ws_connections = $users::getConnectionsByUserID($user_id);
                    foreach ($ws_connections as $ws) {
                        $ws->send($json);
                    }

                    /*Ставим отметку о прочитанности сообщений*/
                    $request = [];
                    $request['section'] = 'clearNewCheck';
                    $json = json_encode($request);

                    if ($users::checkUserID($user_to)) {
                        $ws_connections = $users::getConnectionsByUserID($user_to);
                        foreach ($ws_connections as $ws) {
                            $ws->send($json);
                        }
                    }

                    $cnt = 0; //Если юзер не Ф, то всегда равно 0
                    //Вторая проверка на Ф... Если юзер является Ф
                    if ($flag) {
                        //...то инициируем в переменную $user_id идентификатор оператора
                        try {
                            $res = Yii::$app->db->createCommand('SELECT get_operator_id(:uid) AS operator_id;')
                                ->bindValue(':uid', $user_id)
                                ->queryOne();

                            if ($res) {
                                $user_id = (int)$res['operator_id'];
                            } else {
                                throw new Exception('Not response from "get_operator_id"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }
                        //...считаем кол-во сообщений как для оператора
                        try {
                            $res = Yii::$app->db->createCommand('SELECT get_cnt_msg_by_operator(:uid) AS cnt;')
                                ->bindValue(':uid', $user_id)
                                ->queryOne();

                            if ($res) {
                                $cnt = (int)$res['cnt'];
                            } else {
                                throw new Exception('Not response from "get_cnt_msg_by_operator"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }
                    } else {
                        //Если не Ф, то считаем сообщения как для конкретного юзера
                        try {
                            $res = Yii::$app->db->createCommand('SELECT get_cnt_new_msg(:uid) AS cnt;')
                                ->bindValue(':uid', $user_id)
                                ->queryOne();

                            if ($res) {
                                $cnt = $res['cnt'];
                            } else {
                                throw new Exception('Not response from "get_cnt_new_msg"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }
                    }

                    /*Сообщаем о новом сообщении в title-menu*/
                    if ($users::checkUserID($user_id)) {
                        $ws_connections_to = $users::getConnectionsByUserID($user_id);
                        $request = [];
                        $request['section'] = 'CheckNewMessage';
                        $request['cnt'] = $cnt;
                        $json = json_encode($request);
                        foreach ($ws_connections_to as $ws) {
                            $ws->send($json);
                        }
                    }
                    break;
                case 'ChangeUserByOperator':
                    $user = $data['data']['userID'];

                    //Отправляем сами себе сообщение, что сменили оператора (чтоб в самом мессенджере выйти из него в главное меню)
                    if ($users::checkUserID($user)) {
                        $ws_connections_to = $users::getConnectionsByUserID($user);
                        $request['section'] = 'ChangeUserByOperator';
                        $request['userID'] = $user;
                        $json = json_encode($request);
                        foreach ($ws_connections_to as $ws) {
                            $ws->send($json);
                        }
                    }

                    //удаляем параметр юзера
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

                    //Проверяем, является ли юзер оператором или нет
                    try {
                        $res = Yii::$app->db->createCommand('SELECT user_is_operator(:uid) AS flag;')
                            ->bindValue(':uid', $user)
                            ->queryOne();

                        if ($res) {
                            $flag = (int)$res['flag'];
                        } else {
                            throw new Exception('Not response from "user_is_operator"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    //Если юзер является оператором, то
                    if ($flag) {
                        //...считаем кол-во сообщений как для оператора
                        try {
                            $res = Yii::$app->db->createCommand('SELECT get_cnt_msg_by_operator(:uid) AS cnt;')
                                ->bindValue(':uid', $user)
                                ->queryOne();

                            if ($res) {
                                $cnt = (int)$res['cnt'];
                            } else {
                                throw new Exception('Not response from "get_cnt_msg_by_operator"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }
                    } else {
                        //Если не Ф, то считаем сообщения как для конкретного юзера
                        try {
                            $res = Yii::$app->db->createCommand('SELECT get_cnt_new_msg(:uid) AS cnt;')
                                ->bindValue(':uid', $user)
                                ->queryOne();

                            if ($res) {
                                $cnt = $res['cnt'];
                            } else {
                                throw new Exception('Not response from "get_cnt_new_msg"');
                            }
                        } catch (Exception $ex) {
                            $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                            return false;
                        }
                    }

                    $request['section'] = 'CheckNewMessage';
                    $request['cnt'] = $cnt;
                    $json = json_encode($request);

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
                    if ($users::checkUserID($user_to)) {
                        $ws_connections_to = $users::getConnectionsByUserID($user_to);

                        foreach ($ws_connections_to as $ws) {
                            $ws->send($json);
                        }
                    }

                    break;
                case 'operatorQueue': //Принимает значения с клиента и передаёт всё в очередь
                    $user_id = $data['data']['userID'];

                    //Проверяем, является ли юзер Ф или нет
                    try {
                        $res = Yii::$app->db->createCommand('SELECT user_is_twink(:uid) AS flag;')
                            ->bindValue(':uid', $user_id)
                            ->queryOne();

                        if ($res) {
                            $flag = (int)$res['flag'];
                        } else {
                            throw new Exception('Not response from "user_is_twink"');
                        }
                    } catch (Exception $ex) {
                        $this->sendErrrorStatus($connection, '['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    //Если юзер является Ф и он не онлайн
                    if (($flag) and (!$users::checkUserID($user_id))) {
                        //...то добавляем инфу в очередь
                        $connection_mq = new AMQPStreamConnection('localhost', 5672, 'rmuser', 'rmpassword');
                        $channel = $connection_mq->channel();

                        $channel->exchange_declare('operator_ex_pt', 'direct');
                        $channel->queue_declare('operator_q_pt', false, false, false, false);
                        $channel->queue_bind('operator_q_pt', 'operator_ex_pt', 'new_notif');

                        $dt = [];
                        $dt['userID'] = $user_id;
                        $json_mq = json_encode($dt);

                        $msg = new AMQPMessage($json_mq);
                        $channel->basic_publish($msg, 'operator_ex_pt', 'new_notif');

                        $channel->close();
                        $connection_mq->close();

                        //... и возвращаем статус клиенту
                        $request['section'] = 'queueSucces';
                        $json = json_encode($request);

                        $connection->send($json);
                    }
                    break;
                case 'operatorReciever': //принимет запрос с очереди. Проверяет есть ли новые сообщения у Ф и передаёт результат клиенту Оператора (если он онлайн, конечно)
                    $user_id = $data['data']['userID'];

                    //Проверяем есть ли новое сообщение на данном Ф
                    try {
                        $res = Yii::$app->db->createCommand('SELECT get_cnt_new_msg_by_twink(:uid) AS cnt;')
                            ->bindValue(':uid', $user_id)
                            ->queryOne();

                        if ($res) {
                            $cnt = $res['cnt'];
                        } else {
                            throw new Exception('Not response from "get_cnt_new_msg_by_twink"');
                        }
                    } catch (Exception $ex) {
                        //Тут пока что так, тк обычный способ пеердачи сообщения об ошибке не сработает, ибо клиент - другой ws-сервер и коннект идёт с ним (1)
                        Yii::error('['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    //Составляем список Ф у оператора
                    try {
                        $res = Yii::$app->db->createCommand('CALL get_all_operator_list(:uid);')
                            ->bindValue(':uid', $user_id)
                            ->queryAll();

                        if (empty($res)) {
                            throw new Exception('Not response from "get_all_operator_list"');
                        }
                    } catch (Exception $ex) {
                        //Тут тоже, что и в (1)
                        Yii::error('['.$data['section'].'_ERROR] '.$ex->getMessage());
                        return false;
                    }

                    //Среди списка Ф ищем те, что онлайн, и передаём им оповещения. PS:в идеале должен быть онлайн только 1 Ф (но мы-то знаем этих деб.. юзеров)
                    foreach($res as $r) {
                        if ($users::checkUserID($r['user_id'])) {
                            $request = [];
                            $request['section'] = 'UpdateSelectUser';
                            $request['userID'] = $user_id;
                            $request['cnt'] = $cnt;

                            $json = json_encode($request);
                            $ws_connections = $users::getConnectionsByUserID($r['user_id']);
                            foreach ($ws_connections as $ws) {
                                $ws->send($json);
                            }
                        }
                    }

                    break;
                default:
                    Yii::error('['.$data['section'].'_ERROR] Неизвестная операция. $data: '.print_r($data, true));
                    break;
            }
        };

        $wsWorker->onClose = function ($connection) use (&$users, $wsWorker) {
            if (!(Yii::$app->db->isActive)) {
                Yii::$app->db->open();
            }

            $request = [];

            //удаляем параметр при отключении юзера
            $user_id = $users::removeUserConnect($connection);

            /*Отсылаем всем, что типа челик не онлайн, тем кто сейчас онлайн и с кем у него есть диалог, если все его коннекты удалены из массива*/
            if ($user_id) {
                try {
                    $usrs = Yii::$app->db->createCommand('CALL get_userid_list(:uid);')
                        ->bindValue(':uid', $user_id)
                        ->queryAll();

                    /*if (empty($usrs)) {
                        throw new Exception('Not response from "get_userid_list"');
                    }*/
                } catch (Exception $ex) {
                    $this->sendErrrorStatus($connection, '[wsWorkeronClose_ERROR] '.$ex->getMessage());
                    return false;
                }

                $request['section'] = 'userOffline';
                $request['userID'] = $user_id;
                $json = json_encode($request);
                foreach ($usrs as $us) {
                    $usr_to = $us['user_id'];
                    if ($users::checkUserID($usr_to)) {
                        $ws_connections = $users::getConnectionsByUserID($usr_to);
                        foreach ($ws_connections as $ws) {
                            $ws->send($json);
                        }
                    }
                }
            }
        };
    }

    /**
     * workman websocket start
     */
    public function start()
    {
        $this->initWorker();
        //Сбрасываем параметры для соответсвия Воркеру
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'start';
        if ($this->daemon) {
            $argv[2] = '-d';
        }

        //Запускаем
        Worker::runAll();
    }

    /**
     * workman websocket restart
     */
    public function restart()
    {
        $this->initWorker();
        //Сбрасываем параметры для соответсвия Воркеру
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'restart';
        if ($this->daemon) {
            $argv[2] = '-d';
        }

        if ($this->gracefully) {
            $argv[2] = '-g';
        }

        //Запускаем
        Worker::runAll();
    }

    /**
     * workman websocket stop
     */
    public function stop()
    {
        $this->initWorker();
        //Сбрасываем параметры для соответсвия Воркеру
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'stop';
        if ($this->gracefully) {
            $argv[2] = '-g';
        }

        //Запускаем
        Worker::runAll();
    }

    /**
     * workman websocket reload
     */
    public function reload()
    {
        $this->initWorker();
        //Сбрасываем параметры для соответсвия Воркеру
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'reload';
        if ($this->gracefully) {
            $argv[2] = '-g';
        }

        //Запускаем
        Worker::runAll();
    }

    /**
     * workman websocket status
     */
    public function status()
    {
        $this->initWorker();
        //Сбрасываем параметры для соответсвия Воркеру
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'status';
        if ($this->daemon) {
            $argv[2] = '-d';
        }

        //Запускаем
        Worker::runAll();
    }

    /**
     * workman websocket connections
     */
    public function connections()
    {
        $this->initWorker();
        //Сбрасываем параметры для соответсвия Воркеру
        global $argv;
        $argv[0] = $argv[1];
        $argv[1] = 'connections';

        //Запускаем
        Worker::runAll();
    }
}