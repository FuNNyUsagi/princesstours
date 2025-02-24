<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;

AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
    <!-- Bootstrap core CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <!-- Custom styles for this template -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="/css/styles.css" rel="stylesheet">
    <style>
      html {
        height: auto !important;
      }
    </style>
</head>
<body class="page-plans">
<?php $this->beginBody() ?>

<header>
  <nav class="site-header sticky-top py-1">
    <div class="container header-container d-flex flex-column flex-md-row justify-content-between">
      <div class="header-logo-container">
        <a class="logo-container py-2" href="/">
          <span class="header-slogan">TRAVEL<span>friends</span></span>
          <img src="/img/logo.svg" class="header-logo">
        </a>
      </div>
      <div class="header-menu-container">
        <div class="header-language">
          <div class="select-lang">
            <img src="/img/globe-lang.svg">
            <span>EN</span>
            <div class="select-lang-dropdown-items" style="display: none;">
              <div class="select-lang-dropdown-item" data-lang="ru">RU</div>
              <div class="select-lang-dropdown-item" data-lang="tr">TR</div>
              <div class="select-lang-dropdown-item" data-lang="ch">CH</div>
            </div>
          </div>
        </div>
        <div class="header-menu">
          <a href="/web/user/edit" class="header-menu-account"><?=(!empty(Yii::$app->user->identity->user_name)) ? Yii::$app->user->identity->user_name : 'Account'?></a>
          <?=Html::a('Logout', array('/user/logout'), array('class'=>'header-menu-login'))?>
          <a href="#" class="header-menu-lupe"><img src="/img/lupe-ico-mobile.svg"></a>
        </div>
      </div>
    </div>
  </nav>
</header>

<!-- Begin page content -->
<main role="main" class="container">
    <?= $content ?>
</main>

<div class="footer-mobile">
  <div class="footer-mobile-items">
    <div class="footer-mobile-item footer-mobile-item-homepage active">
      <a href="/web/members"></a>
    </div>
    <div class="footer-mobile-item footer-mobile-item-favorites">
      <a href="/web/favorites"></a>
    </div>
    <div class="footer-mobile-item footer-mobile-item-trips">
      <a href="/web/trips"></a>
    </div>
    <div class="footer-mobile-item footer-mobile-item-messages">
      <a href="/web/messages"></a>
    </div>
    <div class="footer-mobile-item footer-mobile-item-profile">
      <a href="/" data-toggle="modal" data-target="#membershipModal"></a>
    </div>
  </div>
</div>

<footer class="footer">
  <div class="container">
    <div class="row">
      <div class="col-lg-4">
        <div class="footer-logo-container">
          <a class="logo-container py-2" href="/">
            <span class="footer-slogan">TRAVEL<span>friends</span></span>
            <img src="/img/footer-logo.svg" class="footer-logo">
          </a>              
        </div>
      </div>
      <div class="col-lg-8">
        <div class="footer-menu-container">
          <ul>
            <li>About</li>
            <li><a href="/web/pages/about">About Travelgirls</a></li>
            <li><a href="/web/pages/contact">Contact</a></li>
            <li><a href="/web/pages/trips">Trips</a></li>
          </ul>
          <ul>
            <li>Help</li>
            <li><a href="/web/pages/faq">FAQ</a></li>
            <li><a href="/web/pages/traveltips">Travel tips</a></li>
            <li><a href="/web/pages/help">Help</a></li>
          </ul>
          <ul>
            <li>Resources</li>
            <li><a href="/web/pages/privacy">Privacy Policy</a></li>
            <li><a href="/web/pages/terms">Terms and Conditions</a></li>
            <li><a href="/web/pages/resources">Resources</a></li>
          </ul>
        </div>
      </div>
    </div>
    <hr>
    <div class="footer-copyright-container">
      <div class="footer-copyright">Solutions Limited 2010 - <?= date('Y') ?> © travelgirls.com</div>
    </div>
  </div>
</footer>

<!-- Membership modal -->
<div class="modal fade" id="membershipModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <img src="/img/modal-close-ico.svg">
        </button>
      </div>
      <div class="modal-body">
        <h5 class="modal-title">Membership</h5>
        <div class="modal-body-header-text">
          Subscribe to chat with NAME and all others
        </div>
        <div class="modal-body-image">
          <img src="/img/membership-lock.svg">
        </div>
        <div class="modal-body-button">
          <a href="#">Upgrade</a>
        </div>
        <div class="modal-body-footer-text">
          <p>Browse thousands of nice and adventurous girls.Contact your favoured ones right away.Start planning your next travel date.</p>
          <p>If you are not happy, we will return your money with no questions asked.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
<script src="/js/scripts.js"></script>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
