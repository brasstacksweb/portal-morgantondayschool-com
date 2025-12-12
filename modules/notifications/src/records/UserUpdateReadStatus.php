<?php

namespace modules\notifications\records;

use craft\db\ActiveRecord;
use craft\records\Entry;
use craft\records\User;

/**
 * User Update Read Status Record.
 *
 * @property int    $id
 * @property int    $userId
 * @property int    $updateEntryId
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class UserUpdateReadStatus extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%user_update_read_status}}';
    }

    public function rules(): array
    {
        return [
            [['userId', 'updateEntryId'], 'required'],
            [['userId', 'updateEntryId'], 'integer'],
            [['userId', 'updateEntryId'], 'unique', 'targetAttribute' => ['userId', 'updateEntryId']],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    public function getUpdateEntry()
    {
        return $this->hasOne(Entry::class, ['id' => 'updateEntryId']);
    }
}
