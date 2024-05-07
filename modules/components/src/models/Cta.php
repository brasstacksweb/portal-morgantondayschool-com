<?php

namespace modules\components\models;

use craft\base\Model;

abstract class Cta extends Model
{
    public string $title = '';

    abstract public function render(string $content = '', $options = []): void;
}
