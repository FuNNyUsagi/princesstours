<?php

/** @var yii\web\View $this */

$this->title = 'TRAVELFRIENDS.COM - Plans';
?>

<h1 class="page-title-wide">Start your premium now!</h1>
<div class="page-container plans-container">
  <div class="sidebar-container">
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
    <div class="body-header-main-content">
      <h1 class="plans-title">Choose your Plan</h1>
      <div class="plans-items row">
        <div class="plans-item col-lg-4">
          <div class="plans-item-inner">
            <div class="plans-item-time">
              1 month
              <div class="plans-item-description mobile">Perfect for the first time</div>
            </div>
            <div class="plans-item-price">$59</div>
            <div class="plans-item-description">Perfect for the first time</div>
            <div class="plans-item-checkin">
              <input type="radio" class="form-input" id="plan-1" name="plan">
              <label for="plan-1" class="checkbox-label"></label>
            </div>
          </div>
        </div>
        <div class="plans-item col-lg-4">
          <div class="plans-item-inner active">
            <div class="plans-item-time">
              3 month
              <div class="plans-item-description mobile">Great Value!</div>
            </div>
            <div class="plans-item-price">$134</div>
            <div class="plans-item-description">Great Value!</div>
            <div class="plans-item-checkin">
              <input type="radio" class="form-input" id="plan-2" name="plan" checked>
              <label for="plan-2" class="checkbox-label"></label>
            </div>
          </div>
        </div>
        <div class="plans-item col-lg-4">
          <div class="plans-item-inner">
            <div class="plans-item-time">
              6 month
              <div class="plans-item-description mobile">Popular Among Returning Customers</div>
            </div>
            <div class="plans-item-price">$179</div>
            <div class="plans-item-description">Popular Among Returning Customers</div>
            <div class="plans-item-checkin">
              <input type="radio" class="form-input" id="plan-3" name="plan">
              <label for="plan-3" class="checkbox-label"></label>
            </div>
          </div>
        </div>
      </div>
      <div class="plans-button">
        <a href="#">Continue</a>
      </div> 
      <div class="plans-description">
        <div class="plans-description-title">Why should I buy a membership?</div>
        <ul class="plans-description-items">
          <li class="plans-description-item">Browse thousands of nice and adventurous girls.</li>
          <li class="plans-description-item">Contact your favoured ones right away.</li>
          <li class="plans-description-item">Start planning your next travel date.</li>
          <li class="plans-description-item">100% safe & private.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
