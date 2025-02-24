<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\User;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use app\models\Users;
use app\models\Members;

class PagesController extends Controller
{

    public $layout = 'pages';

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Call any functions before action.
     *
     * @return null
     */
    public function beforeAction($action) {
        if (!Yii::$app->user->isGuest) {
            Users::updateOnlineStatus(Yii::$app->user->identity->id);
        }
        
        return true;
    }

    /**
     * Displays empty page.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        /* $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]); */

        return $this->render('contact');
    }

    /**
     * Displays trips page.
     *
     * @return string
     */
    public function actionTrips()
    {
        return $this->render('trips');
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

    /**
     * Displays faq page.
     *
     * @return string
     */
    public function actionFaq()
    {
        return $this->render('faq');
    }

    /**
     * Displays traveltips page.
     *
     * @return string
     */
    public function actionTraveltips()
    {
        return $this->render('traveltips');
    }

    /**
     * Displays help page.
     *
     * @return string
     */
    public function actionHelp()
    {
        return $this->render('help');
    }

    /**
     * Displays privacy page.
     *
     * @return string
     */
    public function actionPrivacy()
    {
        return $this->render('privacy');
    }

    /**
     * Displays terms page.
     *
     * @return string
     */
    public function actionTerms()
    {
        return $this->render('terms');
    }

    /**
     * Displays resources page.
     *
     * @return string
     */
    public function actionResources()
    {
        return $this->render('resources');
    } 
}
