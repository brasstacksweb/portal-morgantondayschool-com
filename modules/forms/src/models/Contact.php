<?php

namespace modules\forms\models;

use Craft;
use craft\helpers\App;
use modules\forms\records\Contact as ContactRecord;
use modules\hubspot\services\Forms;

class Contact extends Form
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $message = '';
    public string $handle = 'contact';

    public function scenarios(): array
    {
        return [
            self::SCENARIO_DEFAULT => [
                'name',
                'email',
                'phone',
                'message',
                'token',
                'handle',
                'pageUrl',
                'scenario',
            ],
        ];
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['name', 'email', 'phone', 'message'], 'required'],
            [['email'], 'email'],
        ]);
    }

    public function attributeTypes(): array
    {
        return array_merge(parent::attributeTypes(), [
            'email' => 'email',
            'phone' => 'tel',
            'message' => 'textarea',
        ]);
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'email' => 'Email Address',
        ]);
    }

    public function attributeSizes(): array
    {
        return array_merge(parent::attributeSizes(), [
            'email' => 'half',
            'phone' => 'half',
        ]);
    }

    public function saveLocal(): bool
    {
        $record = new ContactRecord();

        $record->setAttributes($this->getAttributes(), false);

        return $record->save();
    }

    public function saveRemote(): bool
    {
        return true;
    }

    public function sendConfirmationEmail(): bool
    {
        return true;
    }

    public function sendNotificationEmail(): bool
    {
        return true;

        // return Craft::$app->getMailer()->compose()
        //     ->setTo([])
        //     ->setSubject('Contact Form Submission')
        //     ->setTextBody($this->getAttributesSummaryTable())
        //     ->send();
    }
}
