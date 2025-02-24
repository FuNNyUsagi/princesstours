<?php

/** @var yii\web\View $this */

$this->title = 'TRAVELFRIENDS.COM - Payments';
?>

<h1 class="page-title-wide">Start your premium now!</h1>
<div class="page-container payment-container">
  <div class="body-container">
    <div class="body-header-main-content">
      <h1 class="payment-title">Add payment</h1>
      <div class="payment-card-container">
        <div class="payment-card-price">
          <div class="payment-card-time">1 month</div>
          <div class="payment-card-price-total">59 $</div>
        </div>
        <div class="payment-card-fields">
          <div class="payment-card-field payment-card-field-card-number"">
            <div class="payment-card-field-label">Card number</div>
            <input type="text" placeholder="1111 1111 1111 1111">
          </div>
          <div class="payment-card-field payment-card-field-date">
            <div class="payment-card-field-label">Date</div>
            <input type="text" placeholder="10/26">
          </div>
          <div class="payment-card-field payment-card-field-cvv">
            <div class="payment-card-field-label">CVV</div>
            <input type="text" placeholder="655">
          </div>
        </div>
      </div>
      <div class="payment-button">
        <a href="#">Subscribe</a>
      </div> 
      <div class="payment-description">
        <div class="payment-description-title">100% Secure</div>
        <div class="payment-description-text">All subscriptions are recurring. You can unsubscribe at any time.</div>
      </div>
    </div>
  </div>
</div>
