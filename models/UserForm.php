<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * ContactForm is the model behind the contact form.
 */
class UserForm extends Model
{
    public $email;
    public $password;
    public $name;
    public $surname;
    public $lastname;
    public $sex;
    public $age;
    public $cid;
    public $image;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['email', 'name', 'sex', 'age', 'cid'], 'required'],
            ['email', 'email'],
            [['image'], 'file', 'skipOnEmpty' => false, 'extensions' => 'png, jpeg, jpg, gif'],
        ];
    }

    /**
     * User edit by this model.
     * @param string $email the target email address
     * @return bool whether the model passes validation
     */
    public function edit()
    {
        if ($this->validate()) {

            $this->image->saveAs('uploads/accounts/images/'.$this->image->baseName.'.'.$this->image->extension);

            return true;
        }
        return false;
    }
}
