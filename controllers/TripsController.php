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

class TripsController extends Controller
{

    public $layout = 'trips';

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
        ];
    }

    /**
     * Call any functions before action.
     *
     * @return null
     */
    public function beforeAction($action) {
        Users::updateOnlineStatus(Yii::$app->user->identity->id);
        
        return true;
    }

    /**
     * Displays trips page.
     *
     * @return string
     */
    public function actionIndex()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->render('index');
        }
        else {
            return $this->goHome();
        }
    }
}
