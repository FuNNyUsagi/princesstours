<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\base\Exception;

/**
 * Простоая форма для работы с чатом посредством хранимых процедур, а не ActiveRecord
 */
class Chat extends Model
{
    public static function deleteDialog($data)
    {
        if (isset($data['dialog_id'])) {
            $dialog_id = $data['dialog_id'];
        } else {
            return ['status' => 'err', 'msg' => 'The dialog ID is missing! Please reload the page.'];
        }

        try {
            Yii::$app->db->createCommand('CALL delete_dialog(:did);')
                ->bindValue(':did', $dialog_id)
                ->execute();
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return ['status' => 'err', 'msg' => 'Deletion error! Please reload the page.'];
        }

        return ['status' => 'suc', 'msg' => ''];
    }

    public static function getIdentif($data)
    {
        if (isset($data['uid'])) {
            $user_id = $data['uid'];
        } else {
            return ['status' => 'err', 'msg' => 'The user ID is missing! Please reload the page.'];
        }

        try {
            $res = Yii::$app->db->createCommand('CALL get_identif(:uid);')
                ->bindValue(':uid', $user_id)
                ->queryOne();
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return ['status' => 'err', 'msg' => 'Update user list error! Please reload the page.'];
        }

        return ['status' => 'suc', 'msg' => $res['password']];
    }

    public static function uploadScroll($data)
    {
        if (isset($data['dialog_id'])) {
            $dialog_id = $data['dialog_id'];
        } else {
            return ['status' => 'err', 'msg' => 'The dialog ID is missing! Please reload the page.'];
        }

        if (isset($data['user_id'])) {
            $user_id = $data['user_id'];
        } else {
            return ['status' => 'err', 'msg' => 'The user ID is missing! Please reload the page.'];
        }

        if (isset($data['ofst'])) {
            $ofst = $data['ofst'];
        } else {
            return ['status' => 'err', 'msg' => 'The offset is missing! Please reload the page.'];
        }

        try {
            $msg = Yii::$app->db->createCommand("CALL get_message_list(:did, :o, :l);")
                ->bindValue(':did', $dialog_id)
                ->bindValue(':o', $ofst)
                ->bindValue(':l', 10)
                ->queryAll();
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return ['status' => 'err', 'msg' => 'Unknown error! Please reload the page.'];
        }

        if (count($msg) == 10) {
            $ofst += 10;
        } else {
            $ofst = 0;
        }

        $msg_dt = [];
        /*Группируем сообщения по дате*/
        foreach ($msg as $m) {
            $msg_dt[$m['dateCreate']][] = $m;
        }

        return ['status' => 'suc', 'ofst' => $ofst, 'msg' => $msg_dt];
    }

    public static function getStickers($data)
    {
        if (isset($data['dir'])) {
            $dir = htmlspecialchars($data['dir']);
        } else {
            return ['status' => 'err', 'msg' => 'This sicker pack is missing.'];
        }

        $dir_stikers = $_SERVER['DOCUMENT_ROOT'].'/web/img/stickers/';
        $files_stikers = array_diff( scandir($dir_stikers.$dir), array('..', '.') );
        $out = [];

        if (!empty($files_stikers)) {
            foreach($files_stikers as $file) {
                $out['stickers'][] = '<span><img src="/img/stickers/'.$dir.'/'.$file.'"></span>';
            }
            $out['status'] = 'suc';
        } else {
            $out['status'] = 'err';
            $out['msg'] = 'This sicker pack is missing.';
        }

        return $out;
    }

    public static function uploadImg($data)
    {
        $dt = date('Y-m-d H:i:s');
        $date = date_create($dt);

        $message = $data['message'];
        $dialog_id = $data['dialog_id'];
        $user_id = $data['user_id'];

        $out = [];

        $check = strpos($message, 'base64');
        if ($check !== false) {
            $root = $_SERVER['DOCUMENT_ROOT'];
            $dir = '/uploads/chats/dialog'.$dialog_id.'/';
            $filename = $dir.'picture'.$dialog_id.$user_id.date_format($date,"YmdHis");

            $image_data = explode(';', $message);
            $image_dt = explode('/', $image_data[0]);
            $image_format = '.'.$image_dt[1];

            $msg = $filename.$image_format;

            if (!is_dir($root.$dir)) {
                try {
                    $flg = @mkdir($root.$dir, 0766, true);
                    if ($flg === FALSE) {
                        throw new Exception('Error mkdir');
                    }
                } catch (Exception $e) {
                    Yii::error($e->getMessage());
                    return ['status' => 'err', 'msg' => 'Unknown error! Please reload the page.'];
                }
            }
            try {
                $flg = @file_put_contents($root.$msg, file_get_contents($message));
                if ($flg === FALSE) {
                    throw new Exception('Error file_put_contents');
                }
            } catch (Exception $e) {
                Yii::error($e->getMessage());
                return ['status' => 'err', 'msg' => 'Unknown error! Please reload the page.'];
            }

            $out['status'] = 'suc';
            $out['msg'] = $msg;
        } else {
            $out['status'] = 'err';
        }

        return $out;
    }

    public static function getDialog($data)
    {
        if (isset($data['user_to'])) {
            $user_to = htmlspecialchars($data['user_to']);
        } else {
            return ['status' => 'err', 'msg' => 'User destination is missing.'];
        }
        $user_id = Yii::$app->user->identity->id;

        try {
            $res = Yii::$app->db->createCommand('SELECT get_dialog_id(:uf, :ut) AS dialog_id;')
                ->bindValue(':uf', $user_id)
                ->bindValue(':ut', $user_to)
                ->queryOne();
        } catch (Exception $ex) {
            Yii::error($ex->getMessage());
            return ['status' => 'err', 'msg' => 'Get dialog error! Please reload the page.'];
        }

        return ['status' => 'suc', 'd' => $res['dialog_id'], 'uf' => $user_id];
    }
}
