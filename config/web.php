<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '5NE7EYrv3etCmd-8-g2Oa97K2vhSRg-y',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\Users',
            'enableAutoLogin' => true,
        ],
        'session' => [
            'class' => 'yii\web\DbSession',
            //'db' => 'dbname',
            'sessionTable' => 'tf_session',
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            //'flushInterval' => 1,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    //'exportInterval' => 1,
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                '' => 'site/index',
                'members' => 'members/index',
                'favorites' => 'favorites/index',
                'trips' => 'trips/index',
                'messages' => 'messages/index',
                'plans' => 'plans/index',
                'payments' => 'payments/index',
                'pages' => 'pages/index',
                '<action:\w+>' => 'user/<action>',
                '<action:\w+>' => 'site/<action>',
                '<action:\w+>' => 'members/<action>',
                '<action:\w+>' => 'favorites/<action>',
                '<action:\w+>' => 'trips/<action>',
                '<action:\w+>' => 'messages/<action>',
                '<action:\w+>' => 'plans/<action>',
                '<action:\w+>' => 'payments/<action>',
                '<action:\w+>' => 'pages/<action>',
            ],
        ],
    ],
    /*'as beforeRequest' => [  //if guest user access site so, redirect to login page.
        'class' => 'yii\filters\AccessControl',
        'rules' => [
            [
                'actions' => ['login', 'error', ''],
                'allow' => true,
                'roles' => ['?'],
            ],
            [
                'allow' => true,
                'roles' => ['@'],
            ],
        ],
    ],*/
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
