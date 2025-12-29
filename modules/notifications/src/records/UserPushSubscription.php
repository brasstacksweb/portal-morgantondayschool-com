<?php

namespace modules\notifications\records;

use craft\db\ActiveRecord;
use craft\records\User;

/**
 * User Class Subscription Record.
 *
 * @property int $id
 * @property int $userId
 * @property string endpoint
 * @property string p256dhKey
 * @property string $authKey
 * @property string $lastUsed
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class UserPushSubscription extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%user_push_subscriptions}}';
    }

    public function rules(): array
    {
        return [
            [['userId', 'endpoint', 'p256dhKey', 'authKey', 'lastUsed'], 'required'],
            [['userId'], 'integer'],
            [['endpoint'], 'unique', 'targetAttribute' => ['endpoint']],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }
}
