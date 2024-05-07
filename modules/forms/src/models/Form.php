<?php

namespace modules\forms\models;

use Craft;
use craft\helpers\App;
use modules\components\models\Form as BaseForm;

abstract class Form extends BaseForm
{
    public string $token = '';
    public string $handle = '';
    public string $pageUrl = '';

    public function rules(): array
    {
        return [
            [['handle', 'pageUrl'], 'validateHash'],
            [['token'], 'validateRecaptcha'],
        ];
    }

    public function attributeTypes(): array
    {
        return [
            'token' => 'hidden',
            'handle' => 'hidden',
            'pageUrl' => 'hidden',
            'scenario' => 'hidden',
        ];
    }

    /**
     * Helper function to validate hashed hidden inputs from frontend forms.
     * https://www.yiiframework.com/doc/guide/2.0/en/input-validation#creating-validators.
     *
     * @param string                          $attribute the attribute currently being validated
     * @param mixed                           $params    the value of the "params" given in the rule
     * @param \yii\validators\InlineValidator $validator related InlineValidator instance
     * @param mixed                           $current   the currently validated value of attribute
     */
    public function validateHash($attribute, $params, $validator, $current)
    {
        $unhashed = Craft::$app->getSecurity()->validateData($this->{$attribute});

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
        $endpoint = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => App::env('RECAPTCHA_SECRET_KEY') ?: '',
            'response' => $this->{$attribute},
        ];
        $res = Craft::createGuzzleClient()->request('POST', $endpoint, ['query' => $data]);
        $json = json_decode($res->getBody(), true);

        if (!$json['success'] || $json['score'] < 0.7) {
            $this->addErrors([$attribute => 'Failed recaptcha validation.']);

            return false;
        }

        return true;
    }

    public function getActionPath(): string
    {
        return 'forms-module/forms/submit';
    }

    abstract public function saveLocal(): bool;

    abstract public function saveRemote(): bool;

    abstract public function sendConfirmationEmail(): bool;

    abstract public function sendNotificationEmail(): bool;

    public function getAttributesSummaryTable(array $mapper = []): string
    {
        $table = '';
        $table .= '<h3>Submission Details</h3>';
        $table .= '<table border="0" cellpadding="10">';
        $table .= '<tbody>';

        foreach ($this->getAttributes() as $name => $value) {
            if (!$this->isAttributeActive($name) || $this->getAttributeType($name) === 'hidden') {
                continue;
            }

            $value = is_array($value) ? implode(', ', $value) : nl2br($value);
            // Allow caller to override printed value in table w/ mapping function
            if (array_key_exists($name, $mapper)) {
                $value = $mapper[$name]($value);
            }

            $table .= '<tr><td>';
            $table .= sprintf('<strong>%s</strong><br />', $this->getAttributeLabel($name));
            $table .= $value;
            $table .= '</td></tr>';
        }

        $table .= '</tbody>';
        $table .= '</table>';

        return $table;
    }

    public function getRedirectPath(): string
    {
        return '';
    }

    protected static function splitName(string $name): array
    {
        $parts = explode(' ', $name);
        $lname = count($parts) > 1 ? array_pop($parts) : '';
        $fname = implode(' ', $parts);

        return [$fname, $lname];
    }
}
