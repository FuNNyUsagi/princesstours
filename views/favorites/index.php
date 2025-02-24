<?php

/** @var yii\web\View $this */

$this->title = 'TRAVELFRIENDS.COM - Favorites';
?>
<?php
$this->registerJsFile('@web/js/chat_notif.js');
$user_id = Yii::$app->user->identity->getId();
?>

<div class="page-container members-container">
  <div class="sidebar-container">
    <h1 class="page-title">Favorites</h1>
    <div class="sidebar-menu-container">
      <ul class="sidebar-menu-items">
        <li class="sidebar-menu-item">
          <a href="/web/members" class="sidebar-menu-item-members">Members</a>
        </li>
        <li class="sidebar-menu-item">
          <a href="/web/favorites" class="sidebar-menu-item-favorites active">Favorites</a>
        </li>
        <li class="sidebar-menu-item">
          <a href="/web/trips" class="sidebar-menu-item-trips">Trips</a>
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
    <div class="profile-items row">
      <?php if (!empty($members)): ?>
        <?php foreach ($members as $member) : ?>
          <div class="profile-item col-2">
            <div class="profile-item-inner">
              <div class="profile-image">
                <img src="/uploads/accounts/images/<?=$member['user_image']?>">
                <div data-page="favorites" data-opt="remove" data-hash="<?=$member['user_favorite_hash']?>" class="profile-favorite-btn active" title="Remove member from favorite"></div>
              </div>
              <div class="profile-description">
                <div class="profile-name">
                  <?=$member['user_name']?>
                  <?php if (!empty($member['user_age'])) : ?>
                    <span class="profile-age"><?=$member['user_age']?></span>
                  <?php else : ?>
                    <span class="profile-age">&nbsp;</span>
                  <?php endif; ?>
                </div>
                <div class="profile-online<?=($member['user_last_online'] >= (time() - 300)) ? ' active' : ''?>"></div>
              </div>
              <div class="profile-location">
                <?=$member['country_short_name']?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else : ?>
        Empty content
      <?php endif; ?>
    </div>    
  </div>
</div>
