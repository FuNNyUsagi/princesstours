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
use app\models\Favorites;

class FavoritesController extends Controller
{

    public $layout = 'favorites';

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
     * Displays favorites page.
     *
     * @return string
     */
    public function actionIndex()
    {
        if (!Yii::$app->user->isGuest) {

            $data['members'] = Favorites::getAllFavorites(Yii::$app->user->identity->id);

            return $this->render('index', $data);
        }
        else {
            return $this->goHome();
        }
    }

    /**
     * Add member to favorite.
     *
     * @return string
     */
    public function actionAdd()
    {
        if (Yii::$app->user->isGuest) {
            return $this->asJson(array('status' => 0, 'message' => 'User has no auth on this site!'));
        }

        $favorite = Favorites::addToFavorite(Yii::$app->user->identity->id, Yii::$app->request->post());

        if (!empty($favorite)) {
            return $this->asJson(array('status' => 1, 'message' => 'Member has be add to favorite!', 'hash' => $favorite['user_favorite_hash']));
        }
        else {
            return $this->asJson(array('status' => 0, 'message' => 'Something wrong!'));
        }
    }

    /**
     * Remove member from favorite.
     *
     * @return string
     */
    public function actionRemove()
    {
        if (Yii::$app->user->isGuest) {
            return $this->asJson(array('status' => 0, 'message' => 'User has no auth on this site!'));
        }

        $favorite = Favorites::removeFromFavorite(Yii::$app->user->identity->id, Yii::$app->request->post());

        if (!empty($favorite)) {
            $user = Users::findIdentity($favorite->user_favorite_who_uid);

            return $this->asJson(array('status' => 1, 'message' => 'Member has remove from favorite!', 'hash' => $user['user_hash']));
        }
        else {
            return $this->asJson(array('status' => 0, 'message' => 'Something wrong!'));
        }
    }
}
