<?php

namespace modules\notifications\models;

use modules\components\models\Form as BaseForm;

class Login extends BaseForm
{
    public string $email = '';
    public string $redirect = '';
    public string $submitText = 'Send Magic Link';

    public function __construct(array $config = [])
    {
        if ($config['redirect'] ?? null) {
            $this->redirect = $config['redirect'];
        }

        parent::__construct($config);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            ['email', 'required'],
            ['email', 'email'],
            [['redirect'], 'validateHash'],
        ]);
    }

    public function attributeTypes(): array
    {
        return [
            'email' => 'email',
            'redirect' => 'hidden',
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
