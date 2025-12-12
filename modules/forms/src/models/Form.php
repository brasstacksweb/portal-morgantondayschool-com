<?php

namespace modules\forms\models;

use modules\components\models\Form as BaseForm;

abstract class Form extends BaseForm
{
    public string $handle = '';
    public string $pageUrl = '';

    public function rules(): array
    {
        return [
            [['handle', 'pageUrl'], 'validateHash'],
        ];
    }

    public function attributeTypes(): array
    {
        return [
            'handle' => 'hidden',
            'pageUrl' => 'hidden',
            'scenario' => 'hidden',
        ];
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

    protected static function splitName(string $name): array
    {
        $parts = explode(' ', $name);
        $lname = count($parts) > 1 ? array_pop($parts) : '';
        $fname = implode(' ', $parts);

        return [$fname, $lname];
    }
}
