<?php

try {
    $info = Yii::$app->db->createCommand('CALL get_dialog_info(:uid);')
        ->bindValue(':uid', $user_to)
        ->queryOne();
} catch (Exception $ex) {
    Yii::error($ex->getMessage());
}
$html='';
if ($info) {
    $html = '<div class="messager-image">
              <img class="avatar_image" src="/uploads/accounts/images/'.$info['user_image'].'">
            </div>
            <div class="messager-description">
              <div class="messager-name">'.$info['user_name'].'</div>
              <div class="messager_status"></div>
              <div class="messager-message-typing"><i class="typing">Typing...</i></div>
            </div>';
}

echo $html;