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
use app\models\Chat;

class MessagesController extends Controller
{

    public $layout = 'messages';

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
     * Displays messages page.
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

    /**
     * Displays message page.
     *
     * @return string
     */
    public function actionMessage()
    {
        if (!Yii::$app->user->isGuest) {
            $dialog_id = Yii::$app->request->get('_d');
            $user_id = Yii::$app->request->get('_u');
            $user_to = Yii::$app->request->get('_ut');

            return $this->render('message', [
                'dialog_id' => $dialog_id,
                'user_id' => $user_id,
                'user_to' => $user_to
            ]);
        } else {
            return $this->goHome();
        }
    }

    /**
     * Удаление чата
     *
     * @return json
     */
    public function actionDeleteDialog()
    {
        $result = Chat::deleteDialog($_POST);
        return json_encode($result);
    }

    public function actionGetIdentif()
    {
        $result = Chat::getIdentif($_POST);
        return json_encode($result);
    }

    public function actionUploadScroll()
    {
        $result = Chat::uploadScroll($_POST);
        return json_encode($result);
    }

    public function actionGetStickers()
    {
        $result = Chat::getStickers($_POST);
        return json_encode($result);
    }

    public function actionUploadImg()
    {
        $result = Chat::uploadImg($_POST);
        return json_encode($result);
    }

    public function actionGetDialog()
    {
        $result = Chat::getDialog($_POST);
        return json_encode($result);
    }
}
