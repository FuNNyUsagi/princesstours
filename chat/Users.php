<?php

namespace websoket\chat;
class Users
{
    //Список коннектов
    protected static $users = [];

    /**
     * Функция добавления нового коннекта/юзера в список текущих подключений
     *
     * @param int $user_id id юзера
     * @param object $connection коннект юзера
     */
    public static function setNewConnectByUserID($user_id, $connection)
    {
        if (array_key_exists($user_id, self::$users)) {
            if (!in_array($connection, self::$users[$user_id])) {
                self::$users[$user_id][] = $connection;
            }
        } else {
            self::$users[$user_id][] = $connection;
        }
    }

    /**
     * Функция ищет id юзера в массиве $users и, в случае нахождения, возвращает сисок коннектов
     *
     * @param int $user_id id юзера
     * @return object список коннектов
     */
    public static function getConnectionsByUserID($user_id)
    {
        $ws_connections = self::$users[$user_id];

        return isset($ws_connections) ? $ws_connections : null;
    }

    /**
     * Функция проверяет наличие юзера в массиве $user по его id
     *
     * @param int $user_id id юзера
     * @return boolean результат проверки
     */
    public static function checkUserID($user_id)
    {
        $flag = false;
        if (array_key_exists($user_id, self::$users)) {
            $flag = true;
        }

        return $flag;
    }

    /**
     * Функция удаляет юзера из массива $users по его id
     *
     * @param int $user_id id юзера
     */
    public static function removeUser($user_id)
    {
        if (array_key_exists($user_id, self::$users)) {
            unset(self::$users[$user_id]);
        }
    }

    /**
     * Функция удаляет юзера из массива $users при его отключении. Если все коннекты удалены, то возвращается id юзера
     *
     * @param object $connection отключаемы коннект
     * @return int 0 или id юзера
     */
    public static function removeUserConnect($connection)
    {
        $user_id = 0;
        foreach (self::$users as $k => $u) {
            if (array_search($connection, $u) !== FALSE) {
                $user = array_search($connection, $u);
                unset(self::$users[$k][$user]);

                if (!count(self::$users[$k])) {
                    unset(self::$users[$k]);
                    $user_id = $k;
                }
            }
        }

        return $user_id;
    }
}