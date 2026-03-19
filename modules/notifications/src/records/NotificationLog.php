<?php

namespace modules\notifications\records;

use craft\db\ActiveRecord;
use craft\records\Entry;
use craft\records\User;

/**
 * Notification Log Record.
 *
 * @property int    $id
 * @property int    $userId       - The teacher who sent the notification
 * @property int    $classEntryId - The class the notification was sent for
 * @property int    $pushCount    - Number of push recipients
 * @property int    $emailCount   - Number of email recipients
 * @property string $dateCreated  - When the notification was sent
 * @property string $dateUpdated
 * @property string $uid
 */
class NotificationLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%notification_logs}}';
    }

    public function rules(): array
    {
        return [
            [['userId', 'classEntryId', 'pushCount', 'emailCount'], 'required'],
            [['userId', 'classEntryId', 'pushCount', 'emailCount'], 'integer'],
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
