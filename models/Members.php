<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * Members model
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
 * @property integer $user_created
 * @property integer $user_last_online
 * @property string $auth_key
 * @property string $user_password_reset_token
 * @property string $user_status
 */

class Members extends \yii\db\ActiveRecord
{

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    private static $members = [];
    private static $member = [];

    public static function tableName()
    {
        return '{{%users}}';
    }

    /**
     * Get an member by the given ID.
     *
     * @param string|int $uid the ID to be looked for
     * @return object|null member.
     */
    public static function getMemberFromUid($uid)
    {
        return Members::findOne($uid);
    }

    /**
     * Get all members.
     *
     * @return object|null members.
     */
    public static function getAllMembers($uid = null, $data = null)
    {
        $members = Members::find();
        $members->select('{{%users}}.*, {{%countries}}.*, {{%users_favorites}}.*');
        $members->innerJoin('{{%countries}}', '{{%users}}.cid = {{%countries}}.cid');
        $members->leftJoin('{{%users_favorites}}', '{{%users_favorites}}.user_favorite_from_uid = '.$uid.' AND {{%users_favorites}}.user_favorite_who_uid = {{%users}}.uid AND {{%users_favorites}}.user_favorite_status = 1');
        $members->where(['{{%users}}.user_status' => Members::STATUS_ACTIVE, '{{%countries}}.country_status' => Members::STATUS_ACTIVE]);

        if (!empty($uid)) {
            $members->andWhere(['<>', '{{%users}}.uid', $uid]);
        }

        if (!empty($data['location']) AND $data['location'] != '') {
            $members->andWhere(['LIKE', '{{%countries}}.country_short_name', $data['location']]);
        }

        if (!empty($data['order']) AND $data['order'] == 'last-registered') {
            $members->orderBy('{{%users}}.user_created', 'DESC');
        }
        else if (!empty($data['order']) AND $data['order'] == 'only-online') {
            $members->andWhere(['>=', '{{%users}}.user_last_online', (time() - 300)]);
        }

        $members->orderBy(['{{%users}}.user_last_online' => SORT_DESC]);

        $members->asArray();

        return $members->all();
    }
}
