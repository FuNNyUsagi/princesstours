<?php
use yii\helpers\Html;

/** @var yii\web\View $this */

$this->title = 'TRAVELFRIENDS.COM - Messages';
?>
<?php $this->registerJsFile('@web/js/chat.js');

$this->registerCssFile("@web/css/chat.css");

$user_id = Yii::$app->user->identity->getId();
?>
<div class="page-container messages-container">
    <div class="sidebar-container">
        <h1 class="page-title">Messages</h1>
        <div class="sidebar-menu-container">
            <ul class="sidebar-menu-items">
                <li class="sidebar-menu-item">
                    <a href="/web/members" class="sidebar-menu-item-members">Members</a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="/web/favorites" class="sidebar-menu-item-favorites">Favorites</a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="/web/trips" class="sidebar-menu-item-trips">Trips</a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="/web/messages" class="sidebar-menu-item-messages active">Messages</a><div class="new_msg_cnt hidden"></div>
                </li>
            </ul>
            <div class="sidebar-menu-more-container">
                <a href="/" class="sidebar-menu-more" data-toggle="modal" data-target="#membershipModal">More</a>
            </div>
        </div>
    </div>
    <?= Yii::$app->controller->renderPartial('user_and_pswd_block', ['user_id' => $user_id]); ?>
    <div class="body-container">
        <?= Yii::$app->controller->renderPartial('operator_block', ['user_id' => $user_id]); ?>
        <div class="body-header">
            <div class="body-header-location">
                <input id="search_dialog" type="text" placeholder="Search by name">
                <div class="close_search" title="Clear search"></div>
            </div>
            <div class="body-header-sort">
                <select>
                    <option value="1">All</option>
                    <option value="2">Option 2</option>
                    <option value="3">Option 3</option>
                </select>
            </div>
        </div>
        <div class="body-header-main-content body_messages"></div>
    </div>
</div>
<?= Yii::$app->controller->renderPartial('_modal_dialog'); ?>
