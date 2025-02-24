<?php

if ( (int)Yii::$app->user->identity->getRules() == 2) {
    $html_oper = '<div class="operator_block">
                            <div class="operator_block-location">
                                <label>Select user</label>
                            </div>
                            <div class="operator_block-sort">
                                <select id="select_user">';

    try {
        $users = Yii::$app->db->createCommand('CALL get_twink_list(:uid);')
            ->bindValue(':uid', $user_id)
            ->queryAll();
    } catch (Exception $ex) {
        $html_oper .= '<option value="-1">No data available</option>';
    }

    if (!empty($users)) {
        foreach($users as $u) {
            $html_oper .= '<option value="'.$u['user_id'].'">'.$u['fullname'].'</option>';
        }
    } else {
        $html_oper .= '<option value="-1">No data available</option>';
    }

    $html_oper .= '             </select>
                            </div>
                        </div>';
    echo $html_oper;
}