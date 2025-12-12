<?php

namespace modules\notifications\records;

use craft\db\ActiveRecord;
use craft\records\Entry;
use craft\records\User;

/**
 * User Class Subscription Record.
 *
 * @property int    $id
 * @property int    $userId
 * @property int    $classEntryId
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class UserClassSubscription extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%user_class_subscriptions}}';
    }

    public function rules(): array
    {
        return [
            [['userId', 'classEntryId'], 'required'],
            [['userId', 'classEntryId'], 'integer'],
            [['userId', 'classEntryId'], 'unique', 'targetAttribute' => ['userId', 'classEntryId']],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    public function getClassEntry()
    {
        return $this->hasOne(Entry::class, ['id' => 'classEntryId']);
    }
}
