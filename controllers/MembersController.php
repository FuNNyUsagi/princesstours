<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\User;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use app\models\Users;
use app\models\Members;

class MembersController extends Controller
{

    public $layout = 'members';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['login', 'signup'],
                'rules' => [
                    [
                        'actions' => ['index, logout'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
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
     * Displays members page.
     *
     * @return string
     */
    public function actionIndex()
    {

        if (!Yii::$app->user->isGuest) {

            $data['current_url'] = Url::canonical();
            $data['members'] = Members::getAllMembers(Yii::$app->user->identity->id, Yii::$app->request->get());

            return $this->render('index', $data);
        }
        else {
            return $this->goHome();
        }
    
    }
}
