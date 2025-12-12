<?php

namespace modules\components\models;

use craft\base\Model;
use craft\helpers\App;

class Form extends Model
{
    public string $token = '';
    public string $submitText = 'Submit';

    public function rules(): array
    {
        return [
            [['token'], 'validateRecaptcha'],
        ];
    }

    /**
     * Helper function to validate hashed hidden inputs from frontend forms.
     * https://www.yiiframework.com/doc/guide/2.0/en/input-validation#creating-validators.
     *
     * @param string          $attribute the attribute currently being validated
     * @param mixed           $params    the value of the "params" given in the rule
     * @param InlineValidator $validator related InlineValidator instance
     * @param mixed           $current   the currently validated value of attribute
     */
    public function validateHash($attribute, $params, $validator, $current)
    {
        $unhashed = \Craft::$app->getSecurity()->validateData($this->{$attribute});

        if ($unhashed === false) {
            $this->addErrors([$attribute => 'Invalid hashed input.']);

            return false;
        }

        $this->{$attribute} = $unhashed;

        return true;
    }

    public function validateFile($attribute, $params, $validator, $current)
    {
        $f = UploadedFile::getInstanceByName($attribute);

        if ($f === null) {
            $this->addErrors([$attribute => 'File is required.']);

            return false;
        }

        if ($f->getHasError()) {
            $this->addErrors([$attribute => $f->error]);

            return false;
        }

        $this->{$attribute} = $f;

        return true;
    }

    public function validateRecaptcha($attribute, $params, $validator, $current)
    {
        $endpoint = sprintf(
            'https://recaptchaenterprise.googleapis.com/v1/projects/%s/assessments?key=%s',
            App::env('RECAPTCHA_PROJECT_ID'),
            App::env('RECAPTCHA_API_KEY')
        );

        $data = [
            'event' => [
                'siteKey' => App::env('RECAPTCHA_SITE_KEY'),
                'token' => $this->{$attribute},
                'expectedAction' => 'submit',
            ],
        ];

        try {
            $res = \Craft::createGuzzleClient()->request('POST', $endpoint, ['json' => $data]);
            $json = json_decode($res->getBody(), true);
            $riskAnalysis = $json['riskAnalysis'] ?? [];
            $tokenProperties = $json['tokenProperties'] ?? [];

            if ($tokenProperties['valid'] === false) {
                $this->addErrors([$attribute => 'Invalid recaptcha token: '.$tokenProperties['invalidReason']]);

                return false;
            }

            if ($riskAnalysis['score'] < 0.7) {
                $this->addErrors([$attribute => 'Failed recaptcha validation.']);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            \Craft::error('Recaptcha validation error: '.$e->getMessage(), __METHOD__);
            $this->addErrors([$attribute => 'Recaptcha validation error.']);

            return false;
        }
    }

    public function attributeTypes(): array
    {
        return [
            'token' => 'hidden',
        ];
    }

    public function getAttributeType(string $name): string
    {
        return $this->attributeTypes()[$name] ?? 'text';
    }

    public function attributePlaceholders(): array
    {
        return [];
    }

    public function getAttributePlaceholder(string $name): string
    {
        return $this->attributePlaceholders()[$name] ?? '';
    }

    public function attributeSizes(): array
    {
        return [];
    }

    public function getAttributeSize(string $name): string
    {
        return $this->attributeSizes()[$name] ?? 'full';
    }

    public function attributeOptions(): array
    {
        return [];
    }

    public function getAttributeOptions(string $name): array
    {
        return $this->attributeOptions()[$name] ?? [];
    }

    public function attributePatterns(): array
    {
        return [];
    }

    public function getAttributePattern(string $name): string
    {
        return $this->attributePatterns()[$name] ?? '';
    }

    public function attributeConditionals(): array
    {
        return [];
    }

    public function getAttributeInputConditional(string $name): array
    {
        if (!method_exists($this, 'attributeConditionals')) {
            return [];
        }

        return $this->attributeConditionals()[$name] ?? [];
    }

    public function getActionPath(): string
    {
        return '';
    }

    public function getRedirectPath(): string
    {
        return '';
    }
}
