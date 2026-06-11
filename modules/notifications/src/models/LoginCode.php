<?php

namespace modules\notifications\models;

use modules\components\models\Form as BaseForm;

class LoginCode extends BaseForm
{
    public string $code = '';
    public string $redirect = '/';
    public string $submitText = 'Verify Code';

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
            ['code', 'required'],
            ['code', 'match', 'pattern' => '/^\d{6}$/', 'message' => 'Enter the 6-digit code from your email.'],
        ]);
    }

    public function attributeTypes(): array
    {
        return [
            'code' => 'tel',
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'code' => 'Login code',
        ];
    }

    public function attributePlaceholders(): array
    {
        return [
            'code' => '123456',
        ];
    }

    public function attributePatterns(): array
    {
        return [
            'code' => '\d{6}',
        ];
    }

    public function getActionPath(): string
    {
        return 'notifications/auth/verify-code';
    }

    public function getRedirectPath(): string
    {
        return $this->redirect;
    }
}
