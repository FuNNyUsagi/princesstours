<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\web\IdentityInterface;

/**
 * Users model
 *
 * @property integer $uid
 * @property string $user_hash
 * @property string $user_email
 * @property string $user_password
 * @property string $user_lastname
 * @property string $user_name
 * @property string $user_surname
 * @property integer $user_sex
 * @property integer $user_age
 * @property integer $cid
 * @property string $user_image
 * @property integer $user_created
 * @property integer $user_last_online
 * @property string $auth_key
 * @property string $user_password_reset_token
 * @property string $user_status
 */

class Users extends \yii\db\ActiveRecord implements \yii\web\IdentityInterface
{
    public $uid;
    public $login;
    public $password;
    public $auth_key;

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    public static function tableName()
    {
        return '{{%users}}';
    }

    public function rules()
    {
        return [
            [['user_email', 'user_password'], 'required', 'message' => 'Email field is required'],
            [['user_email'], 'unique', 'message' => 'A user with this email already exists'],
            [['user_email'], 'email', 'message' => 'An incorrect value was entered in the email field'],
            //[['user_password'], 'string', 'min' => 5, 'message' => 'Password cannot be less than 5 characters'],
            //[['user_password'], 'string', 'max' => 10, 'message' => 'Password cannot be more than 10 characters'],
        ];
    }

    public static function getValidationMessages($errors)
    {
        $render = array();

        foreach ($errors as $key => $error) {
          
            foreach ($error as $value) {

                $render[] = $value;

            }

        }

        return $render;
    }

    /**
     * Finds an identity by the given ID.
     *
     * @param string|int $id the ID to be looked for
     * @return IdentityInterface|null the identity object that matches the given ID.
     */
    public static function findIdentity($uid)
    {
        return self::findOne(['uid' => $uid]);
    }

    /**
     * Finds an identity by the given token.
     *
     * @param string $token the token to be looked for
     * @return IdentityInterface|null the identity object that matches the given token.
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return self::findOne(['auth_token' => $token]);
    }

    /**
     * Finds user by username
     *
     * @param string $username
     * @return static|null
     */
    /* public static function findByUsername($username)
    {
        foreach (self::$users as $user) {
            if (strcasecmp($user['username'], $username) === 0) {
                return new static($user);
            }
        }

        return null;
    } */

    /**
     * @return int|string current user ID
     */
    public function getId()
    {
        //return $this->id;
        return $this->getPrimaryKey();
    }

    /**
     * @return string current user auth key
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    public function getPswd()
    {
        return $this->getUserFromId($this->getPrimaryKey())['user_password'];
    }

    public function getRules()
    {
        return $this->getUserFromId($this->getPrimaryKey())['rules'];
    }
    /**
     * @param string $authKey
     * @return bool if auth key is valid for current user
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return $password === md5($this->password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public static function generateAuthKey()
    {
        return Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }

    /**
     * Finds user by login
     *
     * @param string $login
     * @return static|null
     */
    public static function findByLogin($email)
    {
        return $user = self::findOne('email');
    }

    /**
     * Finds user by email and password
     *
     * @param string $email
     * @param string $password
     * @return static|null
     */
    public static function findByLoginPassword($email, $password)
    {
        //if ($this->validate()) {
        if (true) {
            return $user = self::findOne(['user_email' => $email, 'user_password' => md5($password), 'user_status' => self::STATUS_ACTIVE]);
        }
        else {
            return false;
        }
    }

    /**
     * Get all user
     *
     * @return static|null
     */
    public static function getAllUsers()
    {
        return $user = self::findAll(['user_status' => self::STATUS_ACTIVE]);
    }

    /**
     * Get user from hash
     *
     * @param string $hash
     * @return static|null
     */
    public static function getUserFromHash($hash)
    {
        return Users::find()->where(['user_hash' => $hash, 'user_status' => self::STATUS_ACTIVE])->asArray()->one();
    }

    /**
     * Get user from uid
     *
     * @param string $hash
     * @return static|null
     */
    public static function getUserFromId($uid)
    {
        return Users::find()->where(['uid' => $uid, 'user_status' => self::STATUS_ACTIVE])->asArray()->one();
    }

    /**
     * Save user
     *
     * @param string $data
     * @return static|null
     */
    public static function saveUser($data)
    {
        $user = new Users();
        $user->user_hash = md5($data['email'].'_'.time());
        $user->user_email = $data['email'];
        $user->user_password = md5($data['password']);
        $user->user_lastname = '';
        $user->user_name = '';
        $user->user_surname = '';
        $user->user_sex = 1;
        $user->user_age = 0;
        $user->cid = 1;
        $user->user_image = 'account-default.png';
        $user->user_created = time();
        $user->user_last_online = time();
        $user->user_password_reset_token = '';
        $user->user_status = 1;

        return $user->save() ? array('status' => '1', 'data' => $user) : array('status' => '0', 'data' => self::getValidationMessages($user->getErrors()));
    }

    /**
     * Update user
     *
     * @param string $data
     * @return static|null
     */
    public static function updateUser($data)
    {
        $user = new $app->user->findOne($data['uid']);
        $user->user_email = $data['email'];
        $user->user_password = md5($data['password']);
        $user->user_lastname = $data['lastname'];
        $user->user_name = $data['name'];
        $user->user_surname = $data['surname'];
        $user->user_sex = $data['sex'];
        $user->user_age = $data['age'];
        $user->user_cid = $data['cid'];
        $user->user_image = $data['image'];
        $user->user_user_last_online = time();
        $user->user_status = 1;

        return $user->save() ? $user : null;
    }

    /**
     * Update user online status
     *
     * @param string $uid
     * @return static|null
     */
    public static function updateOnlineStatus($uid) {
        $user = Users::findOne($uid);
        $user->user_last_online = time();
        $user->update();
    }
}
