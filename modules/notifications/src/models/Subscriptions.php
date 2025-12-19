<?php

namespace modules\notifications\models;

use craft\elements\Entry;
use modules\components\models\Form as BaseForm;

class Subscriptions extends BaseForm
{
    public array $classes = [];
    public string $submitText = 'Continue';

    public function __construct(array $config = [])
    {
        if ($config['classes'] ?? null) {
            $this->classes = $config['classes'];
        }

        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            ['classes', 'each', 'rule' => ['integer']],
        ];
    }

    public function attributeTypes(): array
    {
        return [
            'classes' => 'checkbox',
            'token' => 'hidden',
        ];
    }

    public function attributeOptions(): array
    {
        $classes = Entry::find()
            ->section('classes')
            ->with('authors')
            ->all();

        return [
            'classes' => array_map(fn ($c) => [
                'label' => sprintf(
                    '%s (%s)',
                    $c->title,
                    implode(', ', array_map(fn ($a) => $a->getFullName(), $c->authors))
                ),
                'value' => $c->id,
            ], $classes),
        ];
    }

    public function getActionPath(): string
    {
        return 'notifications/subscriptions/save';
    }

    public function getRedirectPath(): string
    {
        return 'subscriptions';
    }
}
