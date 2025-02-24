<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\User;
use yii\web\IdentityInterface;
use yii\web\UploadedFile;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use app\models\Users;
use app\models\UserForm;

class UserController extends Controller
{

    public $layout = 'users';

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            /* 'access' => [
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
            ], */
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['get'],
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
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        //return $this->render('index');

        //$this->render('site/error');

        return $this->goHome();
    }

    /**
     * Generate random string action.
     *
     * @return Response|string
     */
    public function actionGeneraterandomstring()
    {
        return \Yii::$app->security->generateRandomString();
    }

    /**
     * Auth action.
     *
     * @return Response|string
     */
    public function actionAuth()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->asJson(array('status' => 1, 'message' => 'User has auth on this site!'));
        }

        $identity = Users::findByLoginPassword(Yii::$app->request->post('email'), Yii::$app->request->post('password'));

        if (!empty($identity) AND Yii::$app->user->login($identity, 604800)) {
            return $this->asJson(array('status' => 1, 'message' => 'Welcome!'));
        }
        else {
            return $this->asJson(array('status' => 0, 'message' => 'Something wrong, email or password incorrect!'));
        }
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    /* public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    } */

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout(true);

        return $this->goHome();
    }

    /**
     * Register action.
     *
     * @return Response|string
     */
    public function actionRegister()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->asJson(array('status' => 1, 'message' => 'User has auth on this site!'));
        }

        $user = Users::SaveUser(Yii::$app->request->post());

        if ($user['status'] == 1) {
            return $this->asJson(array('status' => 1, 'message' => 'Congratulations! You are new user of our site!'));
        }
        else {
            return $this->asJson(array('status' => 0, 'message' => $user['data']));
        }
    }

    /**
     * User edit action.
     *
     * @return Response|string
     */
    /* public function actionEdit()
    {
        if (Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $user = Users::findIdentity(Yii::$app->user->identity->id);

        $data['user'] = $user;
        
        return $this->render('edit', $data);
    } */

    /**
     * User edit action.
     *
     * @return Response|string
     */
    public function actionEdit()
    {
        $model = new UserForm();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if (Users::updateUser(Yii::$app->request->post())) {
                Yii::$app->session->setFlash('message', 'User successfully updated');
            }
            else {
                Yii::$app->session->setFlash('message', 'Something wrong!');
            }
 
            return $this->render('edit', ['model' => $model]);
        }
        else {
            $user = Users::getUserFromId(Yii::$app->user->identity->id);

            $model->email = $user['user_email'];
            $model->name = $user['user_name'];
            $model->surname = $user['user_surname'];
            $model->lastname = $user['user_lastname'];
            $model->sex = $user['user_sex'];
            $model->age = $user['user_age'];
            $model->cid = $user['cid'];
            $model->image = $user['user_image'];

            return $this->render('edit', ['model' => $model]);
        }
    }

}
