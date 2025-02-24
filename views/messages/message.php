<?php

/** @var yii\web\View $this */

$this->title = 'TRAVELFRIENDS.COM - Messages';
?>

<?php
$this->registerJsFile('@web/js/chat_msg.js');
$this->registerJsFile('@web/js/meteorEmoji.min.js');
$this->registerJsFile('@web/js/slick.min.js');
$this->registerJsFile('@web/js/magnific.popup.min.js');

$this->registerCssFile("@web/css/chat_message.css");
$this->registerCssFile("@web/css/slick.css");
$this->registerCssFile("@web/css/magnific.popup.min.css");
?>

<div class="page-container message-container">
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
  <div class="body-container">
    <?= Yii::$app->controller->renderPartial('user_and_pswd_dialog_block', ['user_id' => $user_id, 'dialog_id' => $dialog_id, 'user_to' => $user_to]); ?>
    <div class="body-header-main-content">
      <div class="messager-body">
        <div class="messager-header">
          <div class="messager-left-container">
            <div class="back_flip"></div>
            <?= Yii::$app->controller->renderPartial('user_info', ['user_to' => $user_to]); ?>
          </div>
          <div class="messager-right-container">
            <div class="messager-settings dropdown">
              <div class="dropdown-content">
                <span class="delete_dialog">Delete</span>
              </div>
            </div>
          </div>
        </div>
        <div class="messager-content">
          <div class="messager-content-inner">
            <div class="messager-message-balloons popup-gallery-images"></div>
            <?= Yii::$app->controller->renderPartial('_stickers_block'); ?>
          </div>
        </div>
        <div class="messager-footer">
          <div class="messager-textarea">
            <textarea data-meteor-emoji="true" type="text" id="msg_txt" placeholder="Your message..."></textarea>
          </div>
          <div class="messager-sticker" id="add_telegram_stikers">
            <img src="/img/sticker_label.svg">
          </div>
          <div class="messager-submit">
            <img src="/img/submit-btn.svg">
          </div>
        </div>
        <div class="messager-img"></div>
      </div>
    </div>
  </div>
</div>
<?= Yii::$app->controller->renderPartial('_modal_dialog'); ?>