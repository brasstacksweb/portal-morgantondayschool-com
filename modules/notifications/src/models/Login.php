<?php

namespace modules\notifications\models;

use modules\components\models\Form as BaseForm;

class Login extends BaseForm
{
    public string $email = '';
    public string $submitText = 'Send Magic Link';

    public function rules(): array
    {
        return [
            ['email', 'required'],
            ['email', 'email'],
            [['token'], 'validateRecaptcha'],
        ];
    }

    public function attributeTypes(): array
    {
        return [
            'email' => 'email',
            'token' => 'hidden',
        ];
    }

    public function attributePlaceholders(): array
    {
        return [
            'email' => 'your.email@example.com',
        ];
    }

    public function getActionPath(): string
    {
        return 'notifications/auth/send-magic-link';
    }

    public function getRedirectPath(): string
    {
        return 'login/check-email';
    }
}
