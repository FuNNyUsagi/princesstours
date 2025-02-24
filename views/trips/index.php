<?php

/** @var yii\web\View $this */

$this->title = 'TRAVELFRIENDS.COM - Members';
?>
<?php
$this->registerJsFile('@web/js/chat_notif.js');
$user_id = Yii::$app->user->identity->getId();
?>

<div class="page-container members-container">
  <div class="sidebar-container">
    <h1 class="page-title">Trips</h1>
    <div class="sidebar-menu-container">
      <ul class="sidebar-menu-items">
        <li class="sidebar-menu-item">
          <a href="/web/members" class="sidebar-menu-item-members">Members</a>
        </li>
        <li class="sidebar-menu-item">
          <a href="/web/favorites" class="sidebar-menu-item-favorites">Favorites</a>
        </li>
        <li class="sidebar-menu-item">
          <a href="/web/trips" class="sidebar-menu-item-trips active">Trips</a>
        </li>
        <li class="sidebar-menu-item">
          <a href="/web/messages" class="sidebar-menu-item-messages">Messages</a><div class="new_msg_cnt hidden<?=(int)Yii::$app->user->identity->getRules() == 2 ? ' operator' : '';?>"></div>
        </li>
      </ul>
      <div class="sidebar-menu-more-container">
        <a href="/" class="sidebar-menu-more" data-toggle="modal" data-target="#membershipModal">More</a>
      </div>
    </div>
  </div>
  <input type="hidden" id="user_id" value="<?=$user_id;?>">
  <input type="hidden" id="identif" value="<?=Yii::$app->user->identity->getPswd();?>">
  <div class="body-container">
    Empty content
  </div>
</div>
