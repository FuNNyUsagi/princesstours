<?php
$out = '';
if ((int)Yii::$app->user->identity->getRules() == 2) {
    try {
        $rez_user_check = Yii::$app->db->createCommand('CALL get_twink_user(:uid);')
            ->bindValue(':uid', $user_id)
            ->queryOne();
    } catch (Exception $ex) {
        Yii::error($ex->getMessage());
    }

    if ($rez_user_check) {
        $out = '<input type="hidden" id="user_id" value="'.$rez_user_check['user_id'].'">
            <input type="hidden" id="identif" value="'.$rez_user_check['password'].'">';
    }
} else {
    $out = '<input type="hidden" id="user_id" value="'.$user_id.'">
            <input type="hidden" id="identif" value="'.Yii::$app->user->identity->getPswd().'">';
}

echo $out;