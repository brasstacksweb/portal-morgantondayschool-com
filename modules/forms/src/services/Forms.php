<?php

namespace modules\forms\services;

use Craft;
use modules\forms\models\BusinessUnitContact;
use modules\forms\models\Contact;
use modules\forms\models\Form;

class Forms
{
    public static function newForm(string $handle, array $attrs = [], string $scenario = 'default'): Form
    {
        $f = match ($handle) {
            'contact' => new Contact([
                'pageUrl' => Craft::$app->getRequest()->absoluteUrl,
            ]),
            default => new Form(),
        };

        if ($scenario !== '') {
            $f->setScenario($scenario);
        }

        if (count($attrs) > 0) {
            $f->setAttributes($attrs);
        }

        return $f;
    }
}
