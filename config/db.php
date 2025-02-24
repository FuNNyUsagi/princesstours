<?php

return [
    'class' => 'yii\db\Connection',
        'commandClass' => 'app\extension\CustomMysqlCommand',
    'dsn' => 'mysql:host=ptmysql;dbname=yuripluson_p1db',
    'username' => 'yuripluson_p1db',
    'password' => '3fB898Q*',
    'charset' => 'utf8mb4',
    'tablePrefix' => 'tf_',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
