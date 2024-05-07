<?php

namespace modules\components\models;

use yii\helpers\Html;

class Link extends Cta
{
    public string $body = '';
    public string $url = '';
    public string $target = '';
    public string $rel = '';

    public function render(string $content = '', $options = []): void
    {
        $text = $content ?: $this->title;

        if ($this->url !== '') {
            $options['href'] = $this->url;
        }

        if ($this->target !== '') {
            $options['target'] = $this->target;
        }

        if ($this->rel !== '') {
            $options['rel'] = $this->rel;
        }

        echo Html::tag('a', $text, array_merge([
            'title' => $this->title,
        ], $options));
    }
}
