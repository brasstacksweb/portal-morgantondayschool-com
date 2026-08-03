<?php

namespace modules\athletics\records;

use craft\db\ActiveRecord;
use modules\athletics\services\Signups;

/**
 * Athletics Signup record.
 *
 * @property int     $id
 * @property int     $userId
 * @property int     $teamEntryId
 * @property string  $participantKey
 * @property string  $participantFirstName
 * @property string  $participantLastName
 * @property string  $dateOfBirth
 * @property ?string $shirtSize
 * @property string  $guardianEmail
 * @property string  $guardianPhone
 * @property bool    $interestedInCoaching
 * @property string  $status
 * @property string  $dateCreated
 * @property string  $dateUpdated
 * @property string  $uid
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
            // shirtSize is optional, and RangeValidator skips empty values by
            // default — so this only rejects a non-empty value off the list.
            [['shirtSize'], 'in', 'range' => array_keys(Signups::SHIRT_SIZES)],
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
