<?php
/**
 * список сервисов для работы WorkemanReciever
 */

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\db\Exception;
use yii\helpers\Console;

use Bunny\Channel;
use Bunny\Message;
use Workerman\Worker;
use Workerman\RabbitMQ\Client;
use Workerman\Connection\AsyncTcpConnection;

/**
 *
 * Непосредственно сам WorkemanReciever
 *
 */

class QueueRecieverController extends Controller
{
    public $send;
    public $daemon;
    public $gracefully;

    public $config = [];
    private $ip = 'localhost';
    private $port = '43691';

    private function OpenAsyncTcpConnect() {
        $options_q = [
            "host" => "localhost",
            "port" => 5672,
            "user" => "rmuser",
            "password" => "rmpassword"
        ];

        $ip = isset($this->config['ip']) ? $this->config['ip'] : $this->ip;
        $port = isset($this->config['port']) ? $this->config['port'] : $this->port;

        $ws_connection = new AsyncTcpConnection("ws://{$ip}:{$port}");
        $ws_connection->onConnect = function ($connection) use ($options_q) {
            //echo "connect success\n";

            (new Client($options_q))->connect()->then(function (Client $client) {
                return $client->channel();
            })->then(function (Channel $channel) use ($connection) {
                //echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";
                $channel->consume(
                    function (Message $message, Channel $channel, Client $client) use ($connection) {
                        //echo " [x] Received ", $message->content, "\n";
                        $data = json_decode($message->content, true);
                        //echo " [x] UserID ",$data['userID'], "\n";
                        $request = [];
                        $request['section'] = 'operatorReciever';
                        $request['data']['userID'] = $data['userID'];
                        $json = json_encode($request);
                        $connection->send($json);
                    },
                    'operator_q_pt',
                    '',
                    false,
                    true
                );
            });
        };

        $ws_connection->onError = function ($connection) {
            sleep(5);
            $this->OpenAsyncTcpConnect();
        };
        $ws_connection->onClose = function ($connection) {
            sleep(5);
            $this->OpenAsyncTcpConnect();
        };

        $ws_connection->connect();
    }

    public function options($actionID)
    {
        return ['send', 'daemon', 'gracefully'];
    }

    public function optionAliases()
    {
        return [
            'p' => 'send',
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
        Worker::$pidFile = '/var/run/QueueReciever.pid';
        $worker = new Worker();

        $worker->user = 'www-data';
        $worker->group = 'www-data';
        $worker->reloadable = true;

        $worker->onWorkerStart = function() {
            $this->OpenAsyncTcpConnect();
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