<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\db\Query;
use app\models\Users;

class Countries extends \yii\db\ActiveRecord
{

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    public static function tableName()
    {
        return '{{%countries}}';
    }

    /**
     * Get all countries.
     *
     * @return object|null favorites.
     */
    public static function getAllCountries()
    {
        return self::findAll(['country_status' => self::STATUS_ACTIVE]);
    }

}
