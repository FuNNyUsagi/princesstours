<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\db\Query;
use app\models\Users;

class Favorites extends \yii\db\ActiveRecord
{

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    public static function tableName()
    {
        return '{{%users_favorites}}';
    }

    /**
     * Get all favorites.
     *
     * @return object|null favorites.
     */
    public static function getAllFavorites($uid)
    {
        /* $query = (new \yii\db\Query());
        $query->from('users');
        $query->innerJoin('users_favorites', 'user.uid = users_favorites.user_favorite_from_uid');
        $query->where([
            'users.uid' => Yii::$app->user->uid,
            'users.user_status' => self::STATUS_ACTIVE,
            'users_favorites.user_favorite_status' => self::STATUS_ACTIVE,
        ]);

        return $query->all(); */

        return Favorites::find()
            ->select('{{%users}}.*, {{%countries}}.*, {{%users_favorites}}.*')
            ->innerJoin('{{%users}}', '{{%users}}.uid = {{%users_favorites}}.user_favorite_who_uid')
            ->innerJoin('{{%countries}}', '{{%users}}.cid = {{%countries}}.cid')
            ->where(['{{%users_favorites}}.user_favorite_from_uid' => $uid, '{{%users_favorites}}.user_favorite_status' => Favorites::STATUS_ACTIVE, '{{%users}}.user_status' => Members::STATUS_ACTIVE, '{{%countries}}.country_status' => Members::STATUS_ACTIVE])
            ->asArray()
            ->all();
    }

    /**
     * Get an favorite by the given hash.
     *
     * @param string|int $hash the hash to be looked for
     * @return object|null favorites.
     */
    public static function getFavorite($hash)
    {
        return Members::find()
            ->select('{{%users}}.*, {{%countries}}.*, {{%users_favorites}}.*')
            ->leftJoin('{{%countries}}', '{{%users}}.cid = {{%countries}}.cid')
            ->innerJoin('{{%users_favorites}}', '{{%users}}.uid = {{%users_favorites}}.user_favorite_who_uid')
            ->where(['{{%users_favorites}}.user_favorite_hash' => $hash, '{{%users_favorites}}.user_favorite_status' => Favorites::STATUS_ACTIVE, '{{%users}}.user_status' => Members::STATUS_ACTIVE, '{{%countries}}.country_status' => Members::STATUS_ACTIVE])
            ->asArray()
            ->one();
    }

    /**
     * Get an favorite by the given hash.
     *
     * @param string|int $hash the hash to be looked for
     * @return object|null favorites.
     */
    public static function getFavoriteOne($hash)
    {
        $favorite = Favorites::find();
        $favorite->select('{{%users_favorites}}.*, {{%users}}.*');
        $favorite->innerJoin('{{%users}}', '{{%users}}.uid = {{%users_favorites}}.user_favorite_who_uid');
        $favorite->where(['{{%users_favorites}}.user_favorite_hash' => $hash, '{{%users_favorites}}.user_favorite_status' => Favorites::STATUS_ACTIVE, '{{%users}}.user_status' => Members::STATUS_ACTIVE]);

        return $favorite->one();
    }

    /**
     * Add to favorites.
     *
     * @param string $data
     * @return object|null favorites.
     */
    public static function addToFavorite($uid, $data)
    {

        if (!empty($data) AND $user = Users::getUserFromHash($data['hash'])) {

            $favorite = new Favorites();
            $favorite->user_favorite_hash = md5($user['uid'].'_'.time());
            $favorite->user_favorite_from_uid = $uid;
            $favorite->user_favorite_who_uid = $user['uid'];
            $favorite->user_favorite_created = time();
            $favorite->user_favorite_updated = 0;
            $favorite->user_favorite_status = self::STATUS_ACTIVE;

            return $favorite->save() ? $favorite : null;

        }
        else {

            return null;

        }

    }

    /**
     * Remove from favorites.
     *
     * @param string $data
     * @return object|null favorites.
     */
    public static function removeFromFavorite($uid, $data = null)
    {

        if (!empty($data) AND $favorite = self::getFavoriteOne($data['hash'])) {

            $favorite->user_favorite_updated = time();
            $favorite->user_favorite_status = self::STATUS_INACTIVE;

            return $favorite->save() ? $favorite : null;

        }
        else {

            return null;

        }

    }

}
