<?php

namespace modules\athletics\models;

use modules\athletics\services\Signups;
use modules\components\models\Form as BaseForm;

/**
 * Athletics signup form model.
 *
 * Validates the shape of a registration submission. Business rules (team exists
 * and is open, no duplicate) live in the controller + service, matching the
 * shape-vs-rules split the notifications module uses.
 */
class Signup extends BaseForm
{
    public string $participantFirstName = '';
    public string $participantLastName = '';
    public string $dateOfBirth = '';
    public string $shirtSize = '';
    public string $guardianEmail = '';
    public string $guardianPhone = '';
    public string $status = Signups::STATUS_INTERESTED;
    public array $interestedInCoaching = [];
    public string $teamEntryId = '';
    public string $redirect = '';
    public string $submitText = 'Submit Registration';

    public function scenarios(): array
    {
        return [
            self::SCENARIO_DEFAULT => [
                'participantFirstName',
                'participantLastName',
                'dateOfBirth',
                'shirtSize',
                'guardianEmail',
                'guardianPhone',
                'status',
                'interestedInCoaching',
                'teamEntryId',
                'redirect',
            ],
        ];
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [
                [
                    'participantFirstName',
                    'participantLastName',
                    'dateOfBirth',
                    'guardianEmail',
                    'guardianPhone',
                    'status',
                ],
                'required',
            ],
            ['guardianEmail', 'email'],
            ['dateOfBirth', 'date', 'format' => 'php:Y-m-d', 'message' => 'Enter a valid date of birth.'],
            // Optional — deliberately absent from the required list above, so a
            // parent who is only "interested" is not blocked by it.
            ['shirtSize', 'in', 'range' => array_keys(Signups::SHIRT_SIZES)],
            ['status', 'in', 'range' => [Signups::STATUS_INTERESTED, Signups::STATUS_COMMITTED]],
            ['interestedInCoaching', 'each', 'rule' => ['string']],
            [['teamEntryId'], 'validateHash'],
        ]);
    }

    public function attributeTypes(): array
    {
        return array_merge(parent::attributeTypes(), [
            'dateOfBirth' => 'date',
            'shirtSize' => 'select',
            'guardianEmail' => 'email',
            'guardianPhone' => 'tel',
            'status' => 'radio',
            'interestedInCoaching' => 'checkbox',
            'teamEntryId' => 'hidden',
            'redirect' => 'hidden',
        ]);
    }

    public function attributeLabels(): array
    {
        return [
            'participantFirstName' => "Child's First Name",
            'participantLastName' => "Child's Last Name",
            'dateOfBirth' => 'Date of Birth',
            'shirtSize' => 'Shirt Size',
            'guardianEmail' => 'Parent/Guardian Email',
            'guardianPhone' => 'Parent/Guardian Phone',
            'status' => 'Where are you at?',
            'interestedInCoaching' => 'Coaching',
        ];
    }

    public function attributeSizes(): array
    {
        return [
            'participantFirstName' => 'half',
            'participantLastName' => 'half',
            // Paired so the two sit on one row; a lone 'half' leaves a gap.
            'dateOfBirth' => 'half',
            'shirtSize' => 'half',
            'guardianEmail' => 'half',
            'guardianPhone' => 'half',
        ];
    }

    public function attributeOptions(): array
    {
        return [
            // The leading blank keeps an optional <select> from silently
            // preselecting the first real size. It is deliberately not part of
            // the validation range, which comes from the constant directly.
            'shirtSize' => array_merge(
                [['label' => 'Select a size (optional)', 'value' => '']],
                array_map(
                    fn (string $value, string $label) => ['label' => $label, 'value' => $value],
                    array_keys(Signups::SHIRT_SIZES),
                    Signups::SHIRT_SIZES,
                ),
            ),
            'status' => [
                ['label' => 'Interested — still deciding', 'value' => Signups::STATUS_INTERESTED],
                ['label' => 'Definitely playing', 'value' => Signups::STATUS_COMMITTED],
            ],
            'interestedInCoaching' => [
                ['label' => "I'd be interested in helping coach", 'value' => '1'],
            ],
        ];
    }

    public function getActionPath(): string
    {
        return 'athletics/signups/save';
    }

    public function getRedirectPath(): string
    {
        return $this->redirect;
    }
}
