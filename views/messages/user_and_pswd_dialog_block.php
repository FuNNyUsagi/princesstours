<?php
$out = '';

try {
    $res = Yii::$app->db->createCommand('CALL get_identif(:uid);')
        ->bindValue(':uid', $user_id)
        ->queryOne();
} catch (Exception $ex) {
    Yii::error($ex->getMessage());
}

if ($res) {
    $out = '<input type="hidden" id="user_id" value="'.$user_id.'">
    <input type="hidden" id="identif" value="'.$res['password'].'">
    <input type="hidden" id="dialog_id" value="'.$dialog_id.'">
    <input type="hidden" id="user_to" value="'.$user_to.'">';
}

echo $out;
