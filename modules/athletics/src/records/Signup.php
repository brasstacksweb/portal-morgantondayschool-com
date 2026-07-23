<?php

namespace modules\athletics\records;

use craft\db\ActiveRecord;

/**
 * Athletics Signup record.
 *
 * @property int    $id
 * @property int    $userId
 * @property int    $teamEntryId
 * @property string $participantKey
 * @property string $participantFirstName
 * @property string $participantLastName
 * @property string $dateOfBirth
 * @property string $guardianEmail
 * @property string $guardianPhone
 * @property bool   $interestedInCoaching
 * @property string $status
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class Signup extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%athletics_signups}}';
    }

    public function rules(): array
    {
        return [
            [
                [
                    'userId',
                    'teamEntryId',
                    'participantKey',
                    'participantFirstName',
                    'participantLastName',
                    'dateOfBirth',
                    'guardianEmail',
                    'guardianPhone',
                ],
                'required',
            ],
            [['userId', 'teamEntryId'], 'integer'],
            [['interestedInCoaching'], 'boolean'],
            [['status'], 'in', 'range' => ['interested', 'committed']],
            [
                ['participantKey'],
                'unique',
                'targetAttribute' => ['teamEntryId', 'participantKey'],
                'message' => 'This participant is already registered for this team.',
            ],
        ];
    }
}
