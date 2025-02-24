<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\Countries;

/** @var yii\web\View $this */

$this->title = 'TRAVELFRIENDS.COM - Account edit';
?>

<div class="page-container members-container">
  <div class="sidebar-container">
    <h1 class="page-title">Account</h1>
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
          <a href="/web/messages" class="sidebar-menu-item-messages">Messages</a>
        </li>
      </ul>
      <div class="sidebar-menu-more-container">
        <a href="/" class="sidebar-menu-more" data-toggle="modal" data-target="#membershipModal">More</a>
      </div>
    </div>
  </div>
  <div class="body-container">
    <h2>Profile information</h2>
    <div class="message-box"><?=Yii::$app->session->getFlash('message')?></div>
    <?php $form = ActiveForm::begin(['id' => 'edit-form', 'options' => ['class' => 'form-horizontal']]); ?>

        <?=$form->field($model, 'email')->textInput()->hint('Please, enter you email if you have change them')->label('Email')?>

        <?=$form->field($model, 'name')->textInput()->hint('Please, enter you name')->label('Your name')?>

        <?=$form->field($model, 'surname')->textInput()->hint('Please, enter you surname')->label('Your surname')?>

        <?=$form->field($model, 'lastname')->textInput()->hint('Please, enter you lastname')->label('Your lastname')?>

        <?=$form->field($model, 'sex')->dropdownList((['1' => 'Male', '2' => 'Female']), ['prompt'=>'Select you sex'])->hint('Please, enter you sex')->label('Your sex')?>

        <?=$form->field($model, 'age')->textInput()->hint('Please, enter you age, this field if required')->label('Your age')?>

        <?=$form->field($model, 'cid')->dropdownList(Countries::find()->select(['country_full_name', 'cid'])->indexBy('cid')->column(), ['prompt'=>'Select country'])->hint('Please, enter you country, this field if required')->label('Your country')?>

        <?=$form->field($model, 'image')->fileInput()->label('Your account image')?>

        <div class="form-group">
            <?=Html::submitButton('Save', ['class' => 'btn btn-primary', 'name' => 'edit-button'])?>
        </div>

    <?php ActiveForm::end(); ?>
  </div>
</div>