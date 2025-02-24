<?php
namespace websoket\chat;
use PDO;
class DBConnect
{
    private static $instance;

    private static $servername = "ptmysql";
    private static $username = "yuripluson_p1db";
    private static $password = "3fB898Q*";

    private function __construct()
    {
        //
    }

    private function __clone()
    {
        //
    }

    static public  function getInstance()
    {
        if (static::$instance) {
            //
        } else {
            static::$instance = new PDO('mysql:host='.static::$servername.';dbname=yuripluson_p1db', static::$username, static::$password);
            static::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return static::$instance;
    }
}